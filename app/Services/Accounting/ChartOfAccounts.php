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

    protected bool $loaded = false;

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
            default => '1110', // Cash on Hand / Petty Cash
        };

        // An explicit accountant override on this role wins over everything.
        if (isset($this->overrideByCode[$primaryCode])) {
            return $this->overrideByCode[$primaryCode];
        }

        // Prefer a branch-scoped sub-account of $primaryCode (e.g. "1110-4")
        $branchVariant = $primaryCode.'-'.$branchId;
        if (isset($this->byCode[$branchVariant])) {
            return $this->byCode[$branchVariant];
        }

        return $this->byCode[$primaryCode] ?? null;
    }

    /**
     * Pick a revenue account based on payment kind (consultation / services / medicines / other).
     */
    public function revenueAccountFor(string $kind): ?Account
    {
        $this->loadIfNeeded();

        $code = match (strtolower($kind)) {
            'consultation' => '4110',          // Dermatology & Aesthetics
            'services' => '4110',              // Clinical services revenue
            'medicines', 'pharmacy' => '4210', // Product / Retail Sales
            'other' => '4290',                 // Other Income
            default => '4110',
        };

        return $this->resolve($code);
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

        $this->loaded = true;
    }
}
