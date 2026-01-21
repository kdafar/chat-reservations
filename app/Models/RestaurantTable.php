<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RestaurantTable extends Model
{
    use \App\Models\Concerns\BelongsToBranchScope;

    protected $fillable = ['branch_id', 'name', 'capacity', 'status'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'table_id');
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class, 'restaurant_table_id');
    }
}
