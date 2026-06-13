<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorCompensationLedger extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'fees_snapshot' => 'decimal:3',
        'discount_snapshot' => 'decimal:3',
        'cost_snapshot' => 'decimal:3',
        'profit_snapshot' => 'decimal:3',
        'doctor_cut_amount' => 'decimal:3',
        'rate_snapshot' => 'decimal:3',
        'doctor_id' => 'integer',
        'visit_id' => 'integer',
        'branch_id' => 'integer',
        'settled_payroll_run_id' => 'integer',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
