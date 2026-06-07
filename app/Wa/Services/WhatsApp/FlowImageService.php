<?php

namespace App\Wa\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class FlowImageService
{
    /**
     * Get a cached data-URI (base64) from a storage path (e.g. 'flow/reorder.png').
     */
    public function dataUriFromStorage(string $path, string $disk = 'public', int $ttl = 86400): ?string
    {
        $key = "flow:datauri:{$disk}:{$path}";

        return Cache::remember($key, $ttl, function () use ($disk, $path) {
            if (! Storage::disk($disk)->exists($path)) {
                return null;
            }
            $bytes = Storage::disk($disk)->get($path);
            $mime = $this->guessMime($path, $bytes) ?? 'image/png';

            return $this->toDataUri($bytes, $mime);
        });
    }

    /**
     * Get a cached data-URI from a remote URL (use sparingly).
     */
    public function dataUriFromUrl(string $url, int $ttl = 86400): ?string
    {
        $key = 'flow:datauri:url:'.md5($url);

        return Cache::remember($key, $ttl, function () use ($url) {
            $resp = Http::timeout(5)->get($url);
            if (! $resp->ok()) {
                return null;
            }
            $bytes = $resp->body();
            $mime = $resp->header('Content-Type') ?: 'image/png';

            return $this->toDataUri($bytes, $mime);
        });
    }

    /**
     * Build absolute URL for item images stored locally.
     */
    public function absoluteUrlFromStorage(string $path, string $disk = 'public'): ?string
    {
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return asset(Storage::disk($disk)->url($path));
    }

    private function toDataUri(string $bytes, string $mime): string
    {
        // Tip: keep images reasonably small (<~200KB) for snappy Flow rendering.
        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    private function guessMime(string $path, string $bytes): ?string
    {
        // Prefer extension; fall back to finfo.
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
            return $ext === 'png' ? 'image/png'
                 : ($ext === 'webp' ? 'image/webp' : 'image/jpeg');
        }
        if (function_exists('finfo_open')) {
            $f = finfo_open(FILEINFO_MIME_TYPE);
            $m = finfo_buffer($f, $bytes);
            finfo_close($f);

            return $m ?: null;
        }

        return null;
    }

    public function dataUriFromPath(string $absPath, int $ttl = 86400): ?string
    {
        if (! is_file($absPath)) {
            return null;
        }
        $key = 'flow:datauri:path:'.md5($absPath);

        return Cache::remember($key, $ttl, function () use ($absPath) {
            $bytes = @file_get_contents($absPath);
            if ($bytes === false) {
                return null;
            }
            $mime = function_exists('finfo_open')
                ? tap(finfo_open(FILEINFO_MIME_TYPE), fn ($f) => null) && ($m = finfo_file($f, $absPath)) ? $m : 'image/png'
                : 'image/png';

            return 'data:'.$mime.';base64,'.base64_encode($bytes);
        });
    }
}
