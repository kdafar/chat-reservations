<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Training videos — narrated walkthroughs of the clinic workflows, played
 * inside the admin so staff can watch them without a public link.
 *
 * The files live in storage/app/training (NOT public/), so the only way to
 * reach one is through stream() below, which sits behind the same auth as
 * every other v2 screen. Like the System Guide this is READ-ONLY and open to
 * every authenticated staff member — a receptionist needs the check-in video
 * as much as a manager does, and gating it would only hide training from the
 * people being trained.
 *
 * Titles, descriptions, durations and grouping come from an optional
 * manifest.json alongside the videos, so re-recording or re-labelling a clip
 * needs no code change. A file with no manifest entry still lists, under a
 * title derived from its name.
 *
 * A poster frame is picked up automatically from posters/<name>.jpg.
 */
class TrainingVideosController extends Controller
{
    /** Only these extensions are ever listed or served. */
    private const ALLOWED = ['mp4' => 'video/mp4', 'webm' => 'video/webm'];

    /** Poster images live in a subfolder and are served by the same guard. */
    private const POSTER_TYPES = ['jpg' => 'image/jpeg', 'png' => 'image/png'];

    public function index(Request $request): Response
    {
        return Inertia::render('Training/Index', [
            'videos' => $this->catalogue(),
        ]);
    }

    /**
     * Stream one video. Symfony's BinaryFileResponse honours Range requests,
     * so the player can seek instead of re-downloading from the start.
     */
    public function stream(Request $request, string $file): BinaryFileResponse
    {
        [$path, $mime] = $this->resolve($file, self::ALLOWED, $this->dir());

        return response()->file($path, [
            'Content-Type' => $mime,
            // Training clips are re-recorded in place; don't let a proxy pin an
            // old version for a week.
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /** Poster frame for one video. Same filename, .jpg, under posters/. */
    public function poster(Request $request, string $file): BinaryFileResponse
    {
        [$path, $mime] = $this->resolve($file, self::POSTER_TYPES, $this->dir().DIRECTORY_SEPARATOR.'posters');

        return response()->file($path, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    /**
     * Shared filename guard: reject anything that is not a plain filename with
     * an allowed extension, living directly in the expected folder.
     *
     * @return array{0: string, 1: string} [absolute path, mime type]
     */
    private function resolve(string $file, array $allowed, string $dir): array
    {
        // basename() first: it strips any traversal before the pattern check,
        // so "../../.env" can never resolve outside the training directory.
        $name = basename($file);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1, 404);
        abort_unless(isset($allowed[$ext]), 404);

        $path = $dir.DIRECTORY_SEPARATOR.$name;
        abort_unless(is_file($path), 404);

        return [$path, $allowed[$ext]];
    }

    private function dir(): string
    {
        return storage_path('app/training');
    }

    /**
     * Videos on disk, ordered by filename (the "01-", "02-" prefixes are the
     * running order), decorated with whatever the manifest knows about them.
     */
    private function catalogue(): array
    {
        $dir = $this->dir();
        if (! is_dir($dir)) {
            return [];
        }

        $manifest = $this->manifest();

        $files = array_values(array_filter(
            scandir($dir) ?: [],
            fn ($f) => is_file($dir.DIRECTORY_SEPARATOR.$f)
                && isset(self::ALLOWED[strtolower(pathinfo($f, PATHINFO_EXTENSION))])
        ));
        sort($files, SORT_NATURAL);

        return array_values(array_map(function (string $f, int $i) use ($dir, $manifest) {
            $key = pathinfo($f, PATHINFO_FILENAME);
            $meta = $manifest[$key] ?? [];
            $posterDir = $dir.DIRECTORY_SEPARATOR.'posters'.DIRECTORY_SEPARATOR;
            $poster = $posterDir.$key.'.jpg';
            $thumb = $posterDir.$key.'-thumb.jpg';

            return [
                'key' => $key,
                'file' => $f,
                'n' => $i + 1,
                'url' => route('v2.training.stream', ['file' => $f]),
                // Full frame behind the player, so nothing shifts when playback
                // starts; a tighter crop for the list, which renders it at 104px.
                'poster' => is_file($poster)
                    ? route('v2.training.poster', ['file' => $key.'.jpg'])
                    : null,
                'thumb' => is_file($thumb)
                    ? route('v2.training.poster', ['file' => $key.'-thumb.jpg'])
                    : (is_file($poster) ? route('v2.training.poster', ['file' => $key.'.jpg']) : null),
                'seconds' => isset($meta['seconds']) ? (int) $meta['seconds'] : null,
                'group_en' => $meta['group_en'] ?? null,
                'group_ar' => $meta['group_ar'] ?? null,
                'title_en' => $meta['title_en'] ?? $this->titleFrom($key),
                'title_ar' => $meta['title_ar'] ?? null,
                'desc_en' => $meta['desc_en'] ?? null,
                'desc_ar' => $meta['desc_ar'] ?? null,
            ];
        }, $files, array_keys($files)));
    }

    private function manifest(): array
    {
        $path = $this->dir().DIRECTORY_SEPARATOR.'manifest.json';
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /** "01-add-a-doctor" → "Add a doctor". */
    private function titleFrom(string $key): string
    {
        $words = preg_replace('/^\d+[-_]?/', '', $key);

        return ucfirst(trim(str_replace(['-', '_'], ' ', (string) $words)));
    }
}
