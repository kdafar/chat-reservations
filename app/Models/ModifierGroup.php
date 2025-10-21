<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class ModifierGroup extends Model
{
    use HasTranslations;

    protected $fillable = ['branch_id', 'name', 'is_required', 'min_choices', 'max_choices'];

    protected $casts = ['is_required' => 'boolean'];

    public $translatable = ['name'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function options()
    {
        return $this->hasMany(ModifierOption::class, 'modifier_group_id');
    }

    public function items()
    {
        return $this->belongsToMany(MenuItem::class, 'item_modifier_option',
            'modifier_group_id', 'menu_item_id'
        );
    }

    public function menuItems()
    {
        return $this->belongsToMany(
            MenuItem::class,
            'item_modifier_group', // <-- Pivot table name
            'modifier_group_id',
            'menu_item_id'
        );
    }
}
