<?php

namespace App\Services\WAFlow;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FlowAssets
{
    /** 1×1 PNG “tiny pixel” (raw base64) */
    private const TINY_PIXEL = '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';

    /** File cache settings */
    private string $cacheDisk = 'local';            // storage/app

    private string $cacheDir = 'flow-assets';      // storage/app/flow-assets

    private int $cacheTtlS = 60 * 60 * 24 * 30;  // 30 days

    /** Processing defaults */
    private int $maxSizePx = 64;   // output canvas WxH

    private int $paletteColors = 128;  // reduce colors to save bytes

    private int $pngCompression = 9;    // 0..9

    public function tinyPixel(): string
    {
        return self::TINY_PIXEL;
    }

    /**
     * Accepts array|string|null (JSON/data-URL/raw-b64), returns RAW base64 (PNG), minimized & cached.
     * $opts = ['size' => 64, 'colors' => 128]
     */
    public function imageStrForPicker(null|array|string $asset, array $opts = []): string
    {
        $raw = $this->extractRawBase64($asset);
        if (! $raw) {
            return self::TINY_PIXEL;
        }

        $size = (int) ($opts['size'] ?? $this->maxSizePx);
        $colors = (int) ($opts['colors'] ?? $this->paletteColors);
        $key = $this->cacheKey($raw, $size, $colors);

        // 1) Laravel Cache
        $cached = Cache::get($key);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        // 2) File cache
        $disk = Storage::disk($this->cacheDisk);
        $path = $this->cacheDir.'/'.$key.'.b64';
        if ($disk->exists($path)) {
            $b64 = $disk->get($path);
            if (is_string($b64) && $b64 !== '') {
                Cache::put($key, $b64, $this->cacheTtlS);

                return $b64;
            }
        }

        // 3) Process & store
        $b64 = $this->processRawBase64ToPng($raw, $size, $colors) ?: self::TINY_PIXEL;

        // write atomically
        $tmp = $this->cacheDir.'/'.$key.'.tmp';
        $disk->put($tmp, $b64);
        $disk->move($tmp, $path);

        Cache::put($key, $b64, $this->cacheTtlS);

        return $b64;
    }

    /** Accepts array|string (JSON/data-URL/raw-b64) → returns RAW base64 or null */
    public function extractRawBase64($v): ?string
    {
        if (is_string($v)) {
            $s = trim($v);
            if ($s === '') {
                return null;
            }
            if ($s[0] === '{' || $s[0] === '[') {
                $decoded = json_decode($s, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $v = $decoded;
                } else {
                    return str_starts_with($s, 'data:image') ? (explode(',', $s, 2)[1] ?? null) : $s;
                }
            } else {
                return str_starts_with($s, 'data:image') ? (explode(',', $s, 2)[1] ?? null) : $s;
            }
        }

        if (is_array($v)) {
            if (isset($v['src']) && is_string($v['src'])) {
                $s = $v['src'];

                return str_starts_with($s, 'data:image') ? (explode(',', $s, 2)[1] ?? null) : $s;
            }
            if (isset($v[0]) && is_string($v[0])) {
                $s = $v[0];

                return str_starts_with($s, 'data:image') ? (explode(',', $s, 2)[1] ?? null) : $s;
            }
        }

        return null;
    }

    /** Loads brand icon from storage, minifies to small PNG & caches; returns RAW base64 */
    public function brandIconB64(array $opts = []): string
    {
        $disk = Storage::disk('public');
        $path = 'ui/brand-icon.png';

        if (! $disk->exists($path)) {
            return self::TINY_PIXEL;
        }

        $bytes = $disk->get($path);
        if (! is_string($bytes) || $bytes === '') {
            return self::TINY_PIXEL;
        }

        $raw = base64_encode($bytes);

        return $this->imageStrForPicker($raw, $opts); // reuse processing+cache
    }

    /* ----------------------- internals ----------------------- */

    private function cacheKey(string $rawB64, int $size, int $colors): string
    {
        // stable key derived from content + options
        return 'v2_'.sha1('s'.$size.'c'.$colors.'|'.sha1($rawB64, true));
    }

    private function processRawBase64ToPng(string $rawB64, int $size, int $colors): ?string
    {
        $bin = base64_decode($rawB64, true);
        if ($bin === false || $bin === '') {
            return null;
        }

        // Try Imagick first (best palette reduction), then fallback to GD
        if (extension_loaded('imagick')) {
            $out = $this->imagickMinify($bin, $size, $colors);
            if ($out !== null) {
                return base64_encode($out);
            }
        }

        $out = $this->gdMinify($bin, $size, $colors);

        return $out ? base64_encode($out) : null;
    }

    /** Imagick path: resize → pad to square → quantize → strip → PNG */
    private function imagickMinify(string $bin, int $size, int $colors): ?string
    {
        try {
            $img = new \Imagick;
            $img->readImageBlob($bin);
            $img->setImageBackgroundColor(new \ImagickPixel('transparent'));
            $img = $img->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

            // Preserve aspect, fit into $size
            $w = $img->getImageWidth();
            $h = $img->getImageHeight();
            [$nw, $nh] = $this->fitWithin($w, $h, $size, $size);
            $img->resizeImage($nw, $nh, \Imagick::FILTER_LANCZOS, 1);

            // Letterbox to exact square
            $canvas = new \Imagick;
            $canvas->newImage($size, $size, new \ImagickPixel('transparent'), 'png');
            $x = (int) (($size - $nw) / 2);
            $y = (int) (($size - $nh) / 2);
            $canvas->compositeImage($img, \Imagick::COMPOSITE_DEFAULT, $x, $y);

            // Quantize and strip metadata
            $canvas->quantizeImage(max(16, min($colors, 256)), \Imagick::COLORSPACE_RGB, 0, false, false);
            $canvas->stripImage();

            // Hardest PNG compression
            $canvas->setImageFormat('png');
            $canvas->setOption('png:compression-level', (string) $this->pngCompression);
            $canvas->setOption('png:compression-filter', '5');
            $canvas->setOption('png:compression-strategy', '1');

            $out = $canvas->getImageBlob();
            $canvas->clear();
            $img->clear();

            return $out;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** GD path: resize → pad to square → palette reduce → PNG */
    private function gdMinify(string $bin, int $size, int $colors): ?string
    {
        try {
            $src = imagecreatefromstring($bin);
            if (! $src) {
                return null;
            }

            $w = imagesx($src);
            $h = imagesy($src);

            [$nw, $nh] = $this->fitWithin($w, $h, $size, $size);

            $tmp = imagecreatetruecolor($nw, $nh);
            imagesavealpha($tmp, true);
            imagealphablending($tmp, false);
            $clear = imagecolorallocatealpha($tmp, 0, 0, 0, 127);
            imagefilledrectangle($tmp, 0, 0, $nw, $nh, $clear);
            imagecopyresampled($tmp, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

            $dst = imagecreatetruecolor($size, $size);
            imagesavealpha($dst, true);
            imagealphablending($dst, false);
            $clear2 = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $size, $size, $clear2);

            $x = (int) (($size - $nw) / 2);
            $y = (int) (($size - $nh) / 2);
            imagecopy($dst, $tmp, $x, $y, 0, 0, $nw, $nh);

            // Palette reduce (lossy-ish) to shrink bytes massively
            imagetruecolortopalette($dst, false, max(16, min($colors, 256)));
            imagesavealpha($dst, true);

            ob_start();
            imagepng($dst, null, $this->pngCompression, PNG_ALL_FILTERS);
            $png = ob_get_clean();

            imagedestroy($src);
            imagedestroy($tmp);
            imagedestroy($dst);

            return $png ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** aspect fit within box */
    private function fitWithin(int $w, int $h, int $mw, int $mh): array
    {
        if ($w <= 0 || $h <= 0) {
            return [1, 1];
        }
        $scale = min($mw / $w, $mh / $h);

        return [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];
    }
}
