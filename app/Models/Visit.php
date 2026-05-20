<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    protected $guarded = [];

    use \App\Models\Concerns\BelongsToBranchScope;

    public const STATUS_CREATED = 'created';

    public const STATUS_CHECKED_IN = 'checked_in';

    public const STATUS_AWAITING_DOCTOR = 'awaiting_doctor';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_NO_SHOW = 'no_show';

    protected $casts = [
        'vitals' => 'array',         // { "bp": "120/80", "weight": 70 }
        'prescriptions' => 'array',  // [{ "drug": "...", "dosage": "..." }]
        'lab_requests' => 'array',   // ["X-Ray", "Blood Test"]
        'checked_in_at' => 'datetime',
        'completed_at' => 'datetime',
        'follow_up_date' => 'date',
        'is_prescriptions_printed' => 'boolean',
        'is_labs_printed' => 'boolean',
        'fees_total' => 'decimal:3',
        'discount_total' => 'decimal:3',
        'items_cost_total' => 'decimal:3',
        'items_price_total' => 'decimal:3',
        'profit_total' => 'decimal:3',
        'computed_at' => 'datetime',
        'service_started_at' => 'datetime',
        'accepted_at' => 'datetime',
        'queued_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    // The dynamic room used for this specific visit
    public function room(): BelongsTo
    {
        return $this->belongsTo(RestaurantTable::class, 'restaurant_table_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function visitItems(): HasMany
    {
        return $this->hasMany(VisitItem::class);
    }

    public function doctorCompensationLedger(): HasOne
    {
        return $this->hasOne(DoctorCompensationLedger::class, 'visit_id');
    }

    public function followUpPlans()
    {
        return $this->hasMany(FollowUpPlan::class, 'source_visit_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(VisitPayment::class, 'visit_id');
    }

    /**
     * Convenience totals (safe, non-breaking).
     * Do NOT use for accounting truth; use visit_payments rows.
     */
    public function paidTotal(): string
    {
        // paid + refunded logic depends on your needs; keep conservative
        $sum = $this->payments()
            ->where('status', 'paid')
            ->sum('amount');

        return number_format((float) $sum, 3, '.', '');
    }

    // 3. Safe Accessor for Paid Amount
    // This allows you to call $visit->total_paid safely in Blade
    public function getTotalPaidAttribute(): float
    {
        // The "Null Defense": If payments relation is not loaded, don't crash, just query it.
        return $this->payments->where('status', 'paid')->sum('amount');
    }

    // 4. Safe Accessor for Balance Due
    public function getBalanceDueAttribute(): float
    {
        return $this->fees_total - $this->total_paid;
    }

    public function acceptedBy()
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function stockRequests()
    {
        return $this->hasMany(VisitStockRequest::class);
    }

    public function pendingStockRequest(): HasOne
    {
        return $this->hasOne(\App\Models\VisitStockRequest::class)
            ->where('status', \App\Models\VisitStockRequest::STATUS_PENDING)
            ->latestOfMany('id');
    }

    public function activeStockRequest()
    {
        return $this->hasOne(VisitStockRequest::class)
            ->where('status', VisitStockRequest::STATUS_PENDING)
            ->latestOfMany();
    }

    public function visitPackages()
    {
        return $this->hasMany(VisitPackage::class);
    }

    public function visitCharges()
    {
        return $this->hasMany(VisitCharge::class);
    }
}
