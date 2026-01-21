<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicStockMovement extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $fillable = [
        'branch_id',
        'clinic_item_id',
        'clinic_item_stock_id',
        'related_type',
        'related_id',
        'performed_by',
        'type',
        'qty_change_base',
        'before_qty_base',
        'after_qty_base',
        'notes',
    ];

    protected $casts = [
        'qty_change_base' => 'decimal:4',
        'before_qty_base' => 'decimal:4',
        'after_qty_base' => 'decimal:4',
    ];

    public function related()
    {
        return $this->morphTo();
    }

    public function stock()
    {
        return $this->belongsTo(ClinicItemStock::class, 'clinic_item_stock_id');
    }

    public function clinicItem()
    {
        return $this->belongsTo(ClinicItem::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
