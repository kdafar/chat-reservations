<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicItemStock extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $fillable = [
        'branch_id',
        'clinic_item_id',
        'qty_on_hand_base',
        'min_qty_threshold_base',
        'bin_location',
    ];

    protected $casts = [
        'qty_on_hand_base' => 'decimal:4',
        'min_qty_threshold_base' => 'decimal:4',
    ];

    public function clinicItem()
    {
        return $this->belongsTo(ClinicItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function movements()
    {
        return $this->hasMany(ClinicStockMovement::class, 'clinic_item_stock_id');
    }
}
