<?php

namespace App\Wa\Support\Curator;

use Illuminate\Database\Eloquent\Model;

/**
 * Lightweight stand-in for Awcodes\Curator\Models\Media.
 *
 * The clinic doesn't ship the Curator package (it would collide with the
 * existing spatie media-library `media` table), so the module keeps its own
 * minimal media records on the isolated `wa` connection (wam_curator_media).
 * Only the columns the WhatsApp resources read are present: disk, path, type.
 */
class Media extends Model
{
    protected $connection = 'wa';

    protected $table = 'curator_media';

    protected $guarded = [];

    public function getUrlAttribute(): ?string
    {
        if (! $this->path) {
            return null;
        }

        return \Illuminate\Support\Facades\Storage::disk($this->disk ?: config('curator.disk', 'public'))->url($this->path);
    }
}
