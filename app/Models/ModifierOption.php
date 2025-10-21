<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ModifierOption extends Model
{
    use HasTranslations;

    public $translatable = ['name'];

    protected $fillable = ['modifier_group_id', 'name', 'price_delta', 'is_default'];

    protected $casts = ['is_default' => 'boolean', 'price_delta' => 'decimal:3'];

    public function group()
    {
        return $this->belongsTo(ModifierGroup::class, 'modifier_group_id');
    }
}
