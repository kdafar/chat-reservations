<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

trait HasImageUrl
{
    // Optionally override these in your model if names differ
    protected string $imagePathColumn = 'image_path';

    protected string $imageSrcUrlColumn = 'image_src_url';

    protected string $placeholderAsset = 'images/food-placeholder.jpg';

    protected string $imageDisk = 'public';

    public function getImageUrlAttribute(): string
    {
        $src = $this->{$this->imageSrcUrlColumn} ?? null;
        if ($src && filter_var($src, FILTER_VALIDATE_URL)) {
            return $src;
        }

        $path = $this->{$this->imagePathColumn} ?? null;
        if ($path) {
            $path = ltrim((string) $path, '/');
            $path = preg_replace('#^(public/|storage/)+#', '', $path);

            return Storage::disk($this->imageDisk)->url($path);
        }

        return asset($this->placeholderAsset);
    }
}
