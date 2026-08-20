<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Builds this install's whole icon + logo set from ONE source image.
 *
 *     php artisan brand:generate                 # uses config('tenant.brand.source_image')
 *     php artisan brand:generate path/to/logo.png
 *
 * This is the piece that lets one repo serve many clinics. The generated files
 * are gitignored, so each deployment owns its own branding while the code stays
 * identical — no branch-per-brand, no logo committed to the repo.
 *
 * Square icons are the full logo padded onto a white square. The separate
 * maskable icon is padded harder, because Android crops maskable icons to a
 * circle ~80% of the icon's width and would otherwise slice the wordmark.
 * The wide logo gets its white background removed so it sits correctly on
 * coloured headers (the mobile drawer inverts it to white).
 *
 * Requires ImageMagick (`convert`) on the host.
 */
class GenerateBrandAssets extends Command
{
    protected $signature = 'brand:generate
                            {source? : Source image; defaults to config(tenant.brand.source_image)}
                            {--force : Overwrite existing generated assets without asking}';

    protected $description = "Generate this install's favicons, app icons and logos from one source image";

    public function handle(): int
    {
        if (! $this->hasImageMagick()) {
            $this->error('ImageMagick not found. Install it (apt install imagemagick) and retry.');

            return self::FAILURE;
        }

        $source = $this->argument('source') ?: config('tenant.brand.source_image');
        $source = $this->resolvePath($source);

        if (! $source || ! is_file($source)) {
            $this->error('Source image not found: '.($source ?: '(none configured)'));
            $this->line('Set TENANT_BRAND_IMAGE in .env or pass a path: php artisan brand:generate logo.png');

            return self::FAILURE;
        }

        $this->info('Source: '.$source);

        if (! $this->option('force') && file_exists(public_path('favicon.ico'))
            && ! $this->confirm('Existing brand assets will be overwritten. Continue?', true)) {
            return self::FAILURE;
        }

        $work = storage_path('app/brand-build');
        File::ensureDirectoryExists($work);

        try {
            $this->build($source, $work);
        } finally {
            File::deleteDirectory($work);
        }

        $this->newLine();
        $this->info('Brand assets generated.');
        $this->line('These files are gitignored — they belong to this deployment, not the repo.');

        return self::SUCCESS;
    }

    private function build(string $source, string $work): void
    {
        // Trim the source's own white margin so padding below is predictable.
        $this->magick(['convert', $source, '-fuzz', '3%', '-trim', '+repage', "{$work}/master.png"]);

        // Transparent-background wide logo, for headers that are not white.
        $this->magick(['convert', "{$work}/master.png", '-fuzz', '10%', '-transparent', 'white',
            '-bordercolor', 'none', '-border', '2', '-trim', '+repage', "{$work}/wide.png"]);

        // Square master: logo centred on white at ~86% of the box.
        $this->magick(['convert', "{$work}/master.png", '-resize', '880x880',
            '-background', 'white', '-gravity', 'center', '-extent', '1024x1024',
            '-flatten', "{$work}/square.png"]);

        // Maskable: content inside the ~80%-diameter safe circle.
        $this->magick(['convert', "{$work}/master.png", '-resize', '350x350',
            '-background', 'white', '-gravity', 'center', '-extent', '512x512',
            '-flatten', '-strip', public_path('web-app-manifest-512x512-maskable.png')]);

        $squares = [
            96 => 'favicon-96x96.png',
            180 => 'apple-touch-icon.png',
            192 => 'web-app-manifest-192x192.png',
            512 => 'web-app-manifest-512x512.png',
        ];

        foreach ($squares as $size => $name) {
            $this->magick(['convert', "{$work}/square.png", '-resize', "{$size}x{$size}",
                '-strip', public_path($name)]);
            $this->line("  {$name} ({$size}px)");
        }

        // Multi-resolution .ico for the browser tab.
        $this->magick(['convert', "{$work}/square.png", '-strip',
            '(', '-clone', '0', '-resize', '16x16', ')',
            '(', '-clone', '0', '-resize', '32x32', ')',
            '(', '-clone', '0', '-resize', '48x48', ')',
            '-delete', '0', public_path('favicon.ico')]);
        $this->line('  favicon.ico (16/32/48)');

        // Quantised copies keep the embedded-PNG SVGs small enough to inline.
        $this->magick(['convert', "{$work}/wide.png", '-resize', '640x', '-strip',
            '-depth', '8', '-colors', '200', "{$work}/wide-opt.png"]);
        $this->magick(['convert', "{$work}/square.png", '-resize', '512x512', '-strip',
            '-colors', '200', "{$work}/square-opt.png"]);

        // favicon.svg is also config('app.logo_url')'s default, which the v2
        // boot splash renders in a SQUARE box — so this one must be square or
        // the logo comes out stretched.
        $this->writeSvg("{$work}/square-opt.png", public_path('favicon.svg'), 512, 512);
        $this->line('  favicon.svg (512px square)');

        [$w, $h] = $this->dimensions("{$work}/wide-opt.png");
        foreach ([public_path('images/logo.svg'), base_path('logo.svg')] as $dest) {
            File::ensureDirectoryExists(dirname($dest));
            $this->writeSvg("{$work}/wide-opt.png", $dest, $w, $h);
        }
        $this->line("  logo.svg ({$w}x{$h}, transparent)");

        // Raster wide logo for e-mail, where SVG support is unreliable.
        File::ensureDirectoryExists(public_path('images'));
        $this->magick(['convert', "{$work}/master.png", '-resize', '900x', '-strip',
            '-quality', '90', public_path('logo.jpeg')]);
        $this->magick(['convert', "{$work}/wide.png", '-resize', '900x', '-strip',
            public_path('images/logo.png')]);
        $this->line('  logo.jpeg + images/logo.png');

        $this->writeManifest();
        $this->line('  site.webmanifest');
    }

    /** Wrap a PNG in an SVG so it can be served where an SVG is expected. */
    private function writeSvg(string $png, string $dest, int $w, int $h): void
    {
        $data = base64_encode((string) file_get_contents($png));

        file_put_contents($dest, sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"'
            .' version="1.1" width="%d" height="%d" viewBox="0 0 %d %d">'
            .'<image width="%d" height="%d" xlink:href="data:image/png;base64,%s"/></svg>'."\n",
            $w, $h, $w, $h, $w, $h, $data
        ));
    }

    private function writeManifest(): void
    {
        $manifest = [
            'name' => config('tenant.name.en'),
            'short_name' => config('tenant.brand.short_name'),
            'icons' => [
                ['src' => '/web-app-manifest-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/web-app-manifest-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/web-app-manifest-512x512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'theme_color' => config('tenant.brand.theme_color'),
            'background_color' => config('tenant.brand.theme_color'),
            'display' => 'standalone',
        ];

        file_put_contents(
            public_path('site.webmanifest'),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    /** @return array{0:int,1:int} */
    private function dimensions(string $file): array
    {
        $out = [];
        exec('identify -format "%w %h" '.escapeshellarg($file).' 2>/dev/null', $out);
        $parts = preg_split('/\s+/', trim($out[0] ?? '640 378'));

        return [(int) ($parts[0] ?? 640), (int) ($parts[1] ?? 378)];
    }

    private function magick(array $args): void
    {
        $cmd = implode(' ', array_map(
            // ImageMagick's grouping parens must reach `convert` as literal
            // parens, but bare ( ) would open a subshell — escape, don't quote.
            fn ($a) => in_array($a, ['(', ')'], true) ? '\\'.$a : escapeshellarg($a),
            $args
        ));

        exec($cmd.' 2>&1', $out, $code);

        if ($code !== 0) {
            throw new \RuntimeException('ImageMagick failed: '.$cmd."\n".implode("\n", $out));
        }
    }

    private function resolvePath(?string $p): ?string
    {
        if (blank($p)) {
            return null;
        }

        return str_starts_with($p, '/') ? $p : base_path($p);
    }

    private function hasImageMagick(): bool
    {
        exec('command -v convert', $out, $code);

        return $code === 0;
    }
}
