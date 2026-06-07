<?php

namespace App\Wa\Hub\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a purchasable package of promotional points.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $points
 * @property float $price
 * @property string $currency
 * @property bool $is_active
 */
class PointPackage extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'points',
        'price',
        'currency',
        'is_active',
    ];

    protected $casts = [
        'points' => 'integer',
        'price' => 'float',
        'is_active' => 'boolean',
    ];
}
