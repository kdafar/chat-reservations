<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryArea extends Model
{
    protected $connection = 'wa';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'hub_branch_id',
        'city_id',
        'delivery_fee',
        'min_order_value',
    ];

    /**
     * Get the branch that this delivery area belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(HubBranch::class, 'hub_branch_id');
    }

    /**
     * Get the city for this delivery area.
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
