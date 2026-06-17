<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per automated-posting "role". account_id is the account the system
 * should post that role to; NULL means "use the EVA default" (DEFAULTS below).
 *
 * The defaults ARE the contract the posting engine ships with; the table only
 * lets an accountant override a role without touching code.
 */
class PostingAccountMap extends Model
{
    protected $fillable = ['role', 'default_code', 'account_id'];

    /**
     * role => default EVA account code. Keep in sync with the posting engine
     * (App\Services\Accounting\ChartOfAccounts + AccountingService).
     */
    public const DEFAULTS = [
        'cash' => '1110',
        'card_clearing' => '1130',
        'bank' => '1120',
        'ar' => '1140',
        'inventory' => '1150',
        'cogs' => '5120',
        'doctor_fees' => '5130',
        'accrued_salaries' => '2130',
        'ap' => '2110',
        'import_payable' => '2190',
        'staff_advances' => '1180',
        'salaries_expense' => '6110',
        'eos_expense' => '6120',
        'bad_debt' => '6530',
        'revenue_services' => '4110',
        'revenue_products' => '4210',
        'revenue_other' => '4290',
        'retained_earnings' => '3400',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
