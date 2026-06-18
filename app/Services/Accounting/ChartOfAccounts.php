<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;

/**
 * Cached lookup helper for resolving accounts by code / payment method /
 * revenue kind / branch. The service holds an in-memory map keyed by code
 * so the dozens of journal-entry posts during a busy day don't each issue
 * a SELECT against chart_of_accounts.
 */
class ChartOfAccounts
{
    /** @var array<string, Account> */
    protected array $byCode = [];

    /**
     * Accountant overrides keyed by the role's DEFAULT code → the chosen Account.
     * Only contains roles the accountant has actually remapped (account_id set);
     * unset roles fall through to the built-in default code in $byCode.
     *
     * @var array<string, Account>
     */
    protected array $overrideByCode = [];

    /**
     * Per-entity account links the accountant set on individual branches /
     * partners / gateway accounts (most-specific wins over the global map).
     *
     * @var array<int, Account>        branch_id  => branch cash/operating account
     */
    protected array $branchAccount = [];

    /** @var array<int, int>           branch_id  => partner_id (to resolve partner-level links) */
    protected array $branchPartner = [];

    /** @var array<int, Account>       partner_id => default (services) revenue account */
    protected array $partnerAccount = [];

    /**
     * Active gateway-account settlement links, used to route the debit side of a
     * card/cash receipt to the clearing account that gateway settles into.
     *
     * @var array<int, array{method:string, owner_type:string, partner_id:?int, branch_id:?int, account:Account}>
     */
    protected array $gatewaySettlements = [];

    /** @var array<int, Account>  insurer_id => that insurer's AR account */
    protected array $insurerAr = [];

    /** @var array<int, Account>  clinic_item id => that item's inventory account */
    protected array $itemInventory = [];

    /** @var array<int, Account>  clinic_item id => that item's COGS account */
    protected array $itemCogs = [];

    /** @var array<int, Account>  service id => that service's revenue account */
    protected array $serviceRevenue = [];

    protected bool $loaded = false;

    /** Default cash-on-hand code; a branch's operating account replaces the branch variant of this. */
    protected const CASH_CODE = '1110';

    public function resolve(string $code): ?Account
    {
        $this->loadIfNeeded();

        // An accountant override on this role's default code wins; otherwise the
        // built-in default account for that code.
        return $this->overrideByCode[$code] ?? $this->byCode[$code] ?? null;
    }

    /**
     * Pick a cash/bank account based on payment method + branch.
     * Looks for a branch-scoped account first, falls back to global.
     */
    public function cashAccountFor(?string $method, int $branchId): ?Account
    {
        $this->loadIfNeeded();

        $method = strtolower((string) $method);

        // EVA: patient payments are non-cash. Card/KNET receipts land in the
        // settlement clearing account (1130) until the bank settles them; bank
        // transfers go straight to the CBK current account (1120); physical cash
        // (petty float only) to cash on hand (1110); insurance becomes AR (1140).
        $primaryCode = match ($method) {
            'knet', 'visa', 'mada', 'myfatoorah', 'tap', 'stripe', 'link', 'card' => '1130', // KNET/Card clearing
            'transfer' => '1120', // Bank — CBK
            'insurance' => '1140', // Patient/Insurance receivable
            default => self::CASH_CODE, // Cash on Hand / Petty Cash
        };

        // 1. Most specific: a gateway account configured for this method+scope
        //    routes its receipts to a chosen settlement/clearing account.
        if ($settlement = $this->gatewaySettlementFor($method, $branchId)) {
            return $settlement;
        }

        // 2. An explicit accountant override on this role (global posting map).
        if (isset($this->overrideByCode[$primaryCode])) {
            return $this->overrideByCode[$primaryCode];
        }

        // 3. The branch's own cash/operating account (replaces the "1110-<branch>"
        //    convention) for physical-cash receipts.
        if ($primaryCode === self::CASH_CODE && isset($this->branchAccount[$branchId])) {
            return $this->branchAccount[$branchId];
        }

        // 4. A branch-scoped sub-account of $primaryCode (e.g. "1110-4").
        $branchVariant = $primaryCode.'-'.$branchId;
        if (isset($this->byCode[$branchVariant])) {
            return $this->byCode[$branchVariant];
        }

        return $this->byCode[$primaryCode] ?? null;
    }

    /**
     * Settlement account for a card/cash receipt taken through a gateway account
     * configured for this payment method in scope. Picks the most specific
     * active gateway account with a linked account: branch > partner > system.
     */
    protected function gatewaySettlementFor(string $method, int $branchId): ?Account
    {
        // Map the payment method to the manual gateway method a gateway account
        // is configured under. Bank transfers / insurance never settle here.
        $manual = match ($method) {
            'knet' => 'knet',
            'visa', 'mada', 'card' => 'visa',
            'link', 'myfatoorah', 'tap', 'stripe' => 'link',
            'cash' => 'cash',
            default => null,
        };
        if ($manual === null || empty($this->gatewaySettlements)) {
            return null;
        }

        $partnerId = $this->branchPartner[$branchId] ?? null;
        $best = null;
        $bestRank = -1;
        foreach ($this->gatewaySettlements as $row) {
            if ($row['method'] !== $manual) {
                continue;
            }
            $rank = match (true) {
                $row['owner_type'] === 'branch' && (int) $row['branch_id'] === $branchId => 3,
                $row['owner_type'] === 'partner' && $partnerId !== null && (int) $row['partner_id'] === $partnerId => 2,
                $row['owner_type'] === 'system' => 1,
                default => -1, // a branch/partner row for a different scope — ignore
            };
            if ($rank > $bestRank) {
                $bestRank = $rank;
                $best = $row['account'];
            }
        }

        return $best;
    }

    /**
     * Pick a revenue account based on payment kind (consultation / services / medicines / other).
     */
    public function revenueAccountFor(string $kind, ?int $branchId = null, ?int $serviceId = null): ?Account
    {
        $this->loadIfNeeded();

        // A service pinned to its own revenue account wins (most specific). Only
        // applies where a service context is available — see revenueAccountForService.
        if ($serviceId !== null && ($svc = $this->revenueAccountForService($serviceId))) {
            return $svc;
        }

        $code = match (strtolower($kind)) {
            'consultation' => '4110',          // Dermatology & Aesthetics
            'services' => '4110',              // Clinical services revenue
            'medicines', 'pharmacy' => '4210', // Product / Retail Sales
            'other' => '4290',                 // Other Income
            default => '4110',
        };

        // The clinic's default revenue link applies to services/consultation
        // income (the default 4110 bucket); product & other revenue keep their
        // own codes. Partner link wins over the global map (more specific).
        if ($code === '4110' && $branchId !== null) {
            $partnerId = $this->branchPartner[$branchId] ?? null;
            if ($partnerId !== null && isset($this->partnerAccount[$partnerId])) {
                return $this->partnerAccount[$partnerId];
            }
        }

        return $this->resolve($code);
    }

    /** Accounts-receivable account for an insurer (its own, else default 1140). */
    public function arAccountForInsurer(?int $insurerId): ?Account
    {
        $this->loadIfNeeded();

        if ($insurerId !== null && isset($this->insurerAr[$insurerId])) {
            return $this->insurerAr[$insurerId];
        }

        return $this->resolve('1140');
    }

    /** Inventory account for a stock item (its own, else default 1150). */
    public function inventoryAccountForItem(?int $itemId): ?Account
    {
        $this->loadIfNeeded();

        if ($itemId !== null && isset($this->itemInventory[$itemId])) {
            return $this->itemInventory[$itemId];
        }

        return $this->resolve('1150');
    }

    /** Cost-of-goods account for a stock item (its own, else default 5120). */
    public function cogsAccountForItem(?int $itemId): ?Account
    {
        $this->loadIfNeeded();

        if ($itemId !== null && isset($this->itemCogs[$itemId])) {
            return $this->itemCogs[$itemId];
        }

        return $this->resolve('5120');
    }

    /** Revenue account a service is pinned to, or null to fall back to revenueAccountFor(). */
    public function revenueAccountForService(?int $serviceId): ?Account
    {
        $this->loadIfNeeded();

        return $serviceId !== null ? ($this->serviceRevenue[$serviceId] ?? null) : null;
    }

    /**
     * Expand built-in default codes to also include any account a role has been
     * remapped to. Lets code-based reports (e.g. Cash Flow) follow the posting
     * map while still covering history that was posted under the default code.
     *
     * @param  array<string>  $defaultCodes
     * @return array<string>
     */
    public function effectiveCodes(array $defaultCodes): array
    {
        $this->loadIfNeeded();

        $out = [];
        foreach ($defaultCodes as $code) {
            $out[$code] = true; // history posted under the default code
            $override = $this->overrideByCode[$code] ?? null;
            if ($override && $override->code !== $code) {
                $out[$override->code] = true; // future postings under the remapped account
            }
        }

        return array_keys($out);
    }

    public function refresh(): void
    {
        $this->byCode = [];
        $this->overrideByCode = [];
        $this->branchAccount = [];
        $this->branchPartner = [];
        $this->partnerAccount = [];
        $this->gatewaySettlements = [];
        $this->insurerAr = [];
        $this->itemInventory = [];
        $this->itemCogs = [];
        $this->serviceRevenue = [];
        $this->loaded = false;
        $this->loadIfNeeded();
    }

    protected function loadIfNeeded(): void
    {
        if ($this->loaded) {
            return;
        }

        $accounts = Account::query()->get();
        $this->byCode = $accounts->keyBy('code')->all();

        // Load accountant overrides (role default_code → chosen Account). Wrapped
        // defensively so a missing table (fresh install pre-migration) never
        // breaks posting — it just falls back to the built-in defaults.
        $this->overrideByCode = [];
        try {
            $byId = $accounts->keyBy('id');
            $rows = \App\Models\Accounting\PostingAccountMap::query()
                ->whereNotNull('account_id')
                ->get(['default_code', 'account_id']);
            foreach ($rows as $row) {
                $acc = $byId->get($row->account_id);
                if ($acc) {
                    $this->overrideByCode[$row->default_code] = $acc;
                }
            }
        } catch (\Throwable $e) {
            // table not present yet / migration pending — defaults apply.
        }

        // Per-entity account links (branch cash, partner revenue, gateway
        // settlement). Wrapped defensively so a missing column on a fresh
        // install never breaks posting.
        try {
            $byId = $accounts->keyBy('id');

            foreach (\App\Models\Branch::query()->get(['id', 'partner_id', 'account_id']) as $b) {
                if ($b->partner_id) {
                    $this->branchPartner[(int) $b->id] = (int) $b->partner_id;
                }
                if ($b->account_id && ($acc = $byId->get($b->account_id))) {
                    $this->branchAccount[(int) $b->id] = $acc;
                }
            }

            foreach (\App\Models\Partner::query()->whereNotNull('account_id')->get(['id', 'account_id']) as $p) {
                if ($acc = $byId->get($p->account_id)) {
                    $this->partnerAccount[(int) $p->id] = $acc;
                }
            }

            $gateways = \App\Models\GatewayAccount::query()
                ->where('is_active', true)
                ->whereNotNull('account_id')
                ->get(['owner_type', 'partner_id', 'branch_id', 'credentials', 'account_id']);
            foreach ($gateways as $g) {
                $cred = is_array($g->credentials) ? $g->credentials : [];
                if (($cred['kind'] ?? null) !== 'manual') {
                    continue; // only manual (cash/knet/visa/link) methods settle by method
                }
                $acc = $byId->get($g->account_id);
                if (! $acc) {
                    continue;
                }
                $this->gatewaySettlements[] = [
                    'method' => (string) ($cred['method'] ?? ''),
                    'owner_type' => (string) $g->owner_type,
                    'partner_id' => $g->partner_id,
                    'branch_id' => $g->branch_id,
                    'account' => $acc,
                ];
            }

            foreach (\App\Models\Insurance\Insurer::query()->whereNotNull('ar_account_id')->get(['id', 'ar_account_id']) as $ins) {
                if ($acc = $byId->get($ins->ar_account_id)) {
                    $this->insurerAr[(int) $ins->id] = $acc;
                }
            }

            foreach (\App\Models\ClinicItem::query()
                ->where(fn ($q) => $q->whereNotNull('inventory_account_id')->orWhereNotNull('cogs_account_id'))
                ->get(['id', 'inventory_account_id', 'cogs_account_id']) as $it) {
                if ($it->inventory_account_id && ($acc = $byId->get($it->inventory_account_id))) {
                    $this->itemInventory[(int) $it->id] = $acc;
                }
                if ($it->cogs_account_id && ($acc = $byId->get($it->cogs_account_id))) {
                    $this->itemCogs[(int) $it->id] = $acc;
                }
            }

            foreach (\App\Models\Service::query()->whereNotNull('revenue_account_id')->get(['id', 'revenue_account_id']) as $svc) {
                if ($acc = $byId->get($svc->revenue_account_id)) {
                    $this->serviceRevenue[(int) $svc->id] = $acc;
                }
            }
        } catch (\Throwable $e) {
            // column/table not present yet — per-entity links simply don't apply.
        }

        $this->loaded = true;
    }
}
