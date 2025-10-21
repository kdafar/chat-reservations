<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Menu extends Model
{
    use HasTranslations;

    protected $fillable = ['branch_id', 'name', 'description', 'is_active'];

    public $translatable = ['name', 'description'];

    protected $casts = ['is_active' => 'boolean'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function sections()
    {
        return $this->hasMany(MenuSection::class)->orderBy('sort_order');
    }

    public function items()
    {
        return $this->hasManyThrough(MenuItem::class, MenuSection::class,
            'menu_id',        // MenuSection FK
            'menu_section_id' // MenuItem FK
        );
    }
}
