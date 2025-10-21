<?php

namespace App\Models;

use App\Models\Concerns\HasImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Translatable\HasTranslations;

class MenuItem extends Model
{
    use HasImageUrl, HasTranslations;

    protected $fillable = [
        'menu_section_id',
        'branch_id',
        'name',
        'description',
        'image_path',
        'sku',
        'price',
        'is_available',

        'image_src_url',
        'image_src_hash',
        'image_fingerprint',
        'image_etag',
        'image_last_modified',
        'image_fetched_at',
    ];

    public $translatable = ['name', 'description'];

    protected $casts = [
        'is_available' => 'boolean',
        'price' => 'decimal:3',
        'image_fetched_at' => 'datetime',
    ];

    protected $appends = ['image_url'];

    public function section()
    {
        return $this->belongsTo(MenuSection::class, 'menu_section_id');
    }

    public function menu()
    {
        return $this->hasOneThrough(
            Menu::class, MenuSection::class, 'id', 'id', 'menu_section_id', 'menu_id'
        );
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function modifierGroups()
    {
        return $this->belongsToMany(
            ModifierGroup::class,
            'item_modifier_option',
            'menu_item_id',
            'modifier_group_id'
        );
    }

    public function getImageUrlAttribute(): string
    {
        // 1) Absolute remote source wins (if present)
        if (! empty($this->image_src_url) && filter_var($this->image_src_url, FILTER_VALIDATE_URL)) {
            return $this->image_src_url;
        }

        // 2) Public disk relative path
        $path = $this->image_path ?: null;
        if ($path) {
            // normalize weird prefixes / slashes
            $path = ltrim($path, '/');
            // if someone saved "public/…" or "storage/…", strip it for the public disk
            $path = preg_replace('#^(public/|storage/)+#', '', $path);

            // Build URL using the *public* disk (respects APP_URL + /storage)
            return Storage::disk('public')->url($path);
        }

        // 3) Fallback placeholder (in /public/images)
        return asset('images/food-placeholder.jpg');
    }
}
