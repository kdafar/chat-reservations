<?php

/**
 * Copy Livewire's dist JS into public/livewire/.
 *
 * Livewire normally serves these through a Laravel route
 * (GET /livewire/livewire.min.js). This server's nginx matches *.js first and
 * serves it straight from disk, returning its own 404 before the request ever
 * reaches index.php — which leaves the Livewire bundle missing, so forms fall
 * back to a native browser POST and the admin login dies with a 405.
 *
 * Staging real files at that exact path lets nginx serve them. The route stays
 * registered as a fallback. Run from composer's post-autoload-dump so the copy
 * can never drift from the installed Livewire version.
 */
$dist = __DIR__.'/../vendor/livewire/livewire/dist';
$dest = __DIR__.'/../public/livewire';

if (! is_dir($dist)) {
    fwrite(STDERR, "livewire assets: {$dist} not found, skipping\n");
    exit(0);
}

if (! is_dir($dest) && ! mkdir($dest, 0755, true) && ! is_dir($dest)) {
    fwrite(STDERR, "livewire assets: could not create {$dest}\n");
    exit(1);
}

$copied = 0;
foreach ((array) glob($dist.'/*') as $file) {
    if (is_file($file) && copy($file, $dest.'/'.basename($file))) {
        $copied++;
    }
}

echo "livewire assets: published {$copied} file(s) to public/livewire\n";
