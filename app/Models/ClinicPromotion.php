<?php

namespace App\Models;

use App\Models\Concerns\LogsClinicActivity;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A time-bound automatic discount on clinic items/services. When a matching
 * item is added to a visit, ClinicPromotionService sets the line's
 * discount_amount automatically (see VisitConsoleController::addItem).
 */
class ClinicPromotion extends Model
{
    use LogsClinicActivity;

    // Branch isolation: non-admins only see promotions for their accessible
    // branches (plus global rows where branch_id is null). Admin/super_admin bypass.
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $guarded = [];

    protected $casts = [
        'discount_value' => 'decimal:3',
        'clinic_item_id' => 'integer',
        'branch_id' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    public function clinicItem(): BelongsTo
    {
        return $this->belongsTo(ClinicItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Hand-picked clinic items (scope = 'items'). */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(ClinicItem::class, 'clinic_promotion_items');
    }

    /** Hand-picked packages (scope = 'packages'). */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(ClinicPackage::class, 'clinic_promotion_packages');
    }

    /** Per-unit discount this promotion yields for a unit at the given price (KWD). */
    public function discountPerUnit(float $unitPrice): float
    {
        $raw = $this->discount_type === 'percent'
            ? $unitPrice * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        return round(max(0.0, min($raw, $unitPrice)), 3);
    }
}
