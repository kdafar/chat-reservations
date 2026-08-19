<?php

namespace App\Services;

use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Renders a self-contained HTML string to a real PDF or PNG using headless
 * Chromium.
 *
 * Why Chromium and not a PHP PDF library: the clinic is bilingual and every
 * pure-PHP renderer we could drop in (dompdf & friends) mangles Arabic — no
 * bidi, no glyph shaping. Chromium renders the same page the staff already see
 * when they hit Print, Arabic included, so the PDF we WhatsApp to a patient is
 * the document the clinic signed off on.
 *
 * The HTML must be self-contained (inline CSS, data: images). We render from a
 * file:// temp file, so there is no HTTP round-trip and no session to forward —
 * which also means nothing on the page can pull a stylesheet off the app.
 */
class HtmlToPdfService
{
    /** True when a usable Chromium binary is configured and executable. */
    public function available(): bool
    {
        $bin = (string) config('clinic.pdf.chromium_binary');

        return $bin !== '' && is_file($bin) && is_executable($bin);
    }

    /** Raw PDF bytes for the given HTML. */
    public function toPdf(string $html): string
    {
        return $this->render($html, 'pdf');
    }

    /**
     * Raw PNG bytes for the given HTML, rendered at A4 width so an image report
     * reads like the printed page.
     *
     * Chromium's CLI screenshots the viewport, not the full page, so we render a
     * full A4-height window and then crop the blank tail off — otherwise a
     * half-page report arrives on WhatsApp with a screen of white under it.
     */
    public function toPng(string $html, int $width = 1240, int $height = 1754): string
    {
        return $this->trimBottomWhitespace($this->render($html, 'png', $width, $height));
    }

    /**
     * Write the HTML to a temp file, shell out to Chromium, read the artefact
     * back, and always clean both files up.
     */
    protected function render(string $html, string $format, int $width = 1240, int $height = 1754): string
    {
        if (! $this->available()) {
            throw new \RuntimeException(
                'No Chromium binary available for document rendering — use the printable view instead.'
            );
        }

        $dir = rtrim(sys_get_temp_dir(), '/').'/clinic-render';
        if (! is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        // Scratch HOME for Chromium (see the Process env below for why).
        $home = $dir.'/home';
        if (! is_dir($home)) {
            @mkdir($home.'/.config', 0700, true);
            @mkdir($home.'/.cache', 0700, true);
        }

        $stem = $dir.'/'.Str::uuid()->toString();
        $htmlPath = $stem.'.html';
        $outPath = $stem.'.'.$format;

        file_put_contents($htmlPath, $html);

        // NOTE: deliberately no --user-data-dir. Pointing Chromium at a fresh
        // profile directory makes it hang on first-run setup until the timeout
        // fires; letting it manage its own throwaway profile renders in well
        // under a second and is safe to run concurrently (verified with
        // parallel renders on this box).
        $args = [
            (string) config('clinic.pdf.chromium_binary'),
            '--headless=new',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--no-first-run',
            '--no-default-browser-check',
            // A rendered document has no business reaching the network: no
            // remote fonts, no tracking pixels, no SSRF from typed-in content.
            '--disable-extensions',
            '--disable-background-networking',
            '--disable-component-update',
            '--hide-scrollbars',
        ];

        if ($format === 'pdf') {
            $args[] = '--no-pdf-header-footer';
            $args[] = '--print-to-pdf='.$outPath;
        } else {
            $args[] = '--window-size='.$width.','.$height;
            $args[] = '--screenshot='.$outPath;
        }

        $args[] = 'file://'.$htmlPath;

        try {
            // Chromium MUST have a writable HOME. PHP-FPM (and `artisan serve`)
            // run without one, and a HOME-less Chromium dies instantly with
            // SIGTRAP — "signal 5", no stderr, nothing in the log. So we hand it
            // a scratch home of our own rather than trusting the web user's.
            $process = new Process($args, null, [
                'HOME' => $home,
                'XDG_CONFIG_HOME' => $home.'/.config',
                'XDG_CACHE_HOME' => $home.'/.cache',
            ]);
            $process->setTimeout((float) config('clinic.pdf.timeout_seconds', 60));
            $process->run();

            if (! is_file($outPath) || filesize($outPath) === 0) {
                throw new \RuntimeException(
                    'Document rendering failed'
                    .($process->hasBeenSignaled() ? ' (killed by signal '.$process->getTermSignal().')' : '')
                    .': '.trim($process->getErrorOutput() ?: $process->getOutput())
                );
            }

            return (string) file_get_contents($outPath);
        } finally {
            @unlink($htmlPath);
            @unlink($outPath);
        }
    }

    /**
     * Crop uniform white rows off the bottom of a PNG, keeping a small margin.
     * Returns the input untouched if GD is unavailable or the image is already
     * tight — a cosmetic step must never be able to lose the document.
     */
    protected function trimBottomWhitespace(string $png, int $margin = 40): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $png;
        }

        $img = @imagecreatefromstring($png);
        if ($img === false) {
            return $png;
        }

        try {
            $w = imagesx($img);
            $h = imagesy($img);
            $lastContentRow = null;

            // Sample every 4th pixel across the row: enough to spot a line of
            // text or a table border, ~4x cheaper than reading every pixel.
            for ($y = $h - 1; $y >= 0; $y--) {
                for ($x = 0; $x < $w; $x += 4) {
                    $rgb = imagecolorat($img, $x, $y);
                    if ((($rgb >> 16) & 0xFF) < 248 || (($rgb >> 8) & 0xFF) < 248 || ($rgb & 0xFF) < 248) {
                        $lastContentRow = $y;
                        break 2;
                    }
                }
            }

            if ($lastContentRow === null) {
                return $png;
            }

            $newHeight = min($h, $lastContentRow + $margin);
            if ($newHeight >= $h - 8) {
                return $png;
            }

            $cropped = imagecrop($img, ['x' => 0, 'y' => 0, 'width' => $w, 'height' => $newHeight]);
            if ($cropped === false) {
                return $png;
            }

            try {
                ob_start();
                imagepng($cropped);
                $out = (string) ob_get_clean();

                return $out !== '' ? $out : $png;
            } finally {
                imagedestroy($cropped);
            }
        } catch (\Throwable) {
            return $png;
        } finally {
            imagedestroy($img);
        }
    }
}
