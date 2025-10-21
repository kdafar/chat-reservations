<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class MenuSection extends Model
{
    use HasTranslations;

    protected $fillable = ['menu_id', 'name', 'sort_order'];

    public $translatable = ['name'];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class)->orderBy('id');
    }
}
