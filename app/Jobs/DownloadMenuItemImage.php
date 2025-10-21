<?php

namespace App\Jobs;

use App\Models\MenuItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class DownloadMenuItemImage implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 2;

    public $backoff = [30, 120];

    public function __construct(public int $itemId, public string $url) {}

    public function handle(): void
    {
        $item = MenuItem::find($this->itemId);
        if (! $item) {
            return;
        }

        // quick HEAD/skip if too large (optional)
        // $head = Http::timeout(5)->head($this->url);
        // if ($head->ok() && ((int) $head->header('Content-Length', 0)) > 7_000_000) return;

        $resp = Http::connectTimeout(5)->timeout(10)->retry(1, 200)->get($this->url);
        if (! $resp->ok()) {
            return;
        }

        $img = Image::read($resp->body())->scaleDown(1024, 1024);
        $name = Str::random(40).'.webp';
        $path = 'menu_images/'.$name;

        Storage::disk('public')->put($path, (string) $img->toWebp(80));

        if ($item->image_path && Storage::disk('public')->exists($item->image_path)) {
            Storage::disk('public')->delete($item->image_path);
        }

        $item->image_path = $path;
        $item->save();
    }
}
