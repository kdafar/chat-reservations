<?php

namespace App\Wa\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManagerStatic as Image;

class ImageNormalizer
{
    // Keep under WhatsApp limits
    const MAX_BYTES = 4_800_000; // < 5MB

    const MAX_EDGE = 4096;      // px

    /**
     * Download any image URL, convert to WhatsApp-safe JPEG, return local path.
     *
     * @return array [bool $ok, ?string $localPath, ?string $err]
     */
    public function toLocalJpeg(string $url): array
    {
        try {
            // 1) Fetch
            $resp = Http::timeout(20)->get($url);
            if (! $resp->successful()) {
                return [false, null, 'Download failed: HTTP '.$resp->status()];
            }
            $bytes = $resp->body();
            if ($bytes === '' || $bytes === null) {
                return [false, null, 'Empty image body'];
            }

            // 2) Configure driver (gd by default; use imagick if you have it)
            // Image::configure(['driver' => 'gd']); // optional
            // Image::configure(['driver' => 'imagick']); // if imagick installed

            // 3) Decode
            $img = Image::make($bytes);

            // 4) Scale down (preserve aspect, prevent upscale)
            $w = $img->width();
            $h = $img->height();
            if ($w > self::MAX_EDGE || $h > self::MAX_EDGE) {
                $img->resize(self::MAX_EDGE, self::MAX_EDGE, function ($c) {
                    $c->aspectRatio();
                    $c->upsize();
                });
            }

            // 5) Encode JPEG under size limit
            $quality = 85;
            $jpeg = (string) $img->encode('jpg', $quality);
            while (strlen($jpeg) > self::MAX_BYTES && $quality > 40) {
                $quality -= 10;
                $jpeg = (string) $img->encode('jpg', $quality);
            }
            if (strlen($jpeg) > self::MAX_BYTES) {
                return [false, null, 'Image too large after compression'];
            }

            // 6) Save temp file
            $name = 'waimg_'.Str::random(16).'.jpg';
            $path = storage_path('app/tmp/'.$name);
            @mkdir(dirname($path), 0775, true);
            file_put_contents($path, $jpeg);

            return [true, $path, null];

        } catch (\Throwable $e) {
            Log::warning('Image normalize failed', ['err' => $e->getMessage()]);

            return [false, null, $e->getMessage()];
        }
    }
}
