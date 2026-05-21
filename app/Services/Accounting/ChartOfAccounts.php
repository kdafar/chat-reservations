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

    protected bool $loaded = false;

    public function resolve(string $code): ?Account
    {
        $this->loadIfNeeded();

        return $this->byCode[$code] ?? null;
    }

    /**
     * Pick a cash/bank account based on payment method + branch.
     * Looks for a branch-scoped account first, falls back to global.
     */
    public function cashAccountFor(?string $method, int $branchId): ?Account
    {
        $this->loadIfNeeded();

        $method = strtolower((string) $method);

        // Bank-deposit methods land in bank accounts; cash methods to cash on hand.
        $primaryCode = match ($method) {
            'knet', 'visa', 'mada', 'myfatoorah', 'tap', 'stripe', 'link', 'card' => '1020', // Bank
            'transfer' => '1020',
            'insurance' => '1110', // Patient receivable (insurance becomes AR)
            default => '1010', // Cash on Hand
        };

        // Prefer a branch-scoped sub-account of $primaryCode (e.g. "1010-4")
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
            'consultation' => '4010',
            'services' => '4020',
            'medicines', 'pharmacy' => '4030',
            'other' => '4040',
            default => '4010',
        };

        return $this->byCode[$code] ?? null;
    }

    public function refresh(): void
    {
        $this->byCode = [];
        $this->loaded = false;
        $this->loadIfNeeded();
    }

    protected function loadIfNeeded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->byCode = Account::query()->get()->keyBy('code')->all();
        $this->loaded = true;
    }
}
