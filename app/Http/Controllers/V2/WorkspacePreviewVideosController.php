<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * "How to use" videos for the v3 visit workspace PREVIEW.
 *
 * Kept apart from the live Training Videos page on purpose: those teach the
 * system staff use today, and a clip of a Vitals tab that does not exist in
 * v2 yet would send people looking for it. Same storage model as training —
 * files in storage/app/workspace-preview-videos (not public/), streamed only
 * through this controller behind the admin login.
 *
 * Every chapter is recorded twice, once per language:
 *
 *   01-tour.en.mp4, 01-tour.ar.mp4
 *   posters/01-tour.en.jpg, posters/01-tour.ar.jpg
 *   manifest.json → { "01-tour": { title_en, title_ar, desc_en, desc_ar, role, seconds_en, seconds_ar } }
 *
 * Deleting this file, its route, the page and the folder removes it entirely.
 */
class WorkspacePreviewVideosController extends Controller
{
    private const LANGS = ['en', 'ar'];

    public function index(Request $request): Response
    {
        abort_unless($request->user() && $request->user()->can('view_any_visits'), 403);

        return Inertia::render('WorkspacePreview/Videos', [
            'chapters' => $this->chapters(),
        ]);
    }

    public function stream(Request $request, string $file): BinaryFileResponse
    {
        abort_unless($request->user() && $request->user()->can('view_any_visits'), 403);
        $path = $this->resolve($file, ['mp4'], $this->dir());

        return response()->file($path, ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, max-age=300']);
    }

    public function poster(Request $request, string $file): BinaryFileResponse
    {
        abort_unless($request->user() && $request->user()->can('view_any_visits'), 403);
        $path = $this->resolve($file, ['jpg'], $this->dir().DIRECTORY_SEPARATOR.'posters');

        return response()->file($path, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400']);
    }

    /** Plain filename, allowed extension, directly inside $dir — nothing else. */
    private function resolve(string $file, array $exts, string $dir): string
    {
        $name = basename($file);
        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $name) === 1, 404);
        abort_unless(in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $exts, true), 404);
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        abort_unless(is_file($path), 404);

        return $path;
    }

    private function dir(): string
    {
        return storage_path('app/workspace-preview-videos');
    }

    /**
     * One entry per chapter found on disk (either language), in filename
     * order, each carrying whichever language versions exist.
     *
     * @return array<int, array<string, mixed>>
     */
    private function chapters(): array
    {
        $dir = $this->dir();
        if (! is_dir($dir)) {
            return [];
        }

        $manifest = [];
        if (is_file($dir.'/manifest.json')) {
            $decoded = json_decode((string) file_get_contents($dir.'/manifest.json'), true);
            $manifest = is_array($decoded) ? $decoded : [];
        }

        $keys = [];
        foreach (scandir($dir) ?: [] as $f) {
            if (preg_match('/^(.+)\.(en|ar)\.mp4$/', $f, $m)) {
                $keys[$m[1]] = true;
            }
        }
        $keys = array_keys($keys);
        sort($keys, SORT_NATURAL);

        return array_values(array_map(function (string $key, int $i) use ($dir, $manifest) {
            $meta = $manifest[$key] ?? [];
            $versions = [];
            foreach (self::LANGS as $lang) {
                $file = "{$key}.{$lang}.mp4";
                if (! is_file("{$dir}/{$file}")) {
                    continue;
                }
                $poster = "{$key}.{$lang}.jpg";
                $versions[$lang] = [
                    'file' => $file,
                    'url' => route('v2.workspace-preview.videos.stream', ['file' => $file]),
                    'poster' => is_file("{$dir}/posters/{$poster}")
                        ? route('v2.workspace-preview.videos.poster', ['file' => $poster])
                        : null,
                    'seconds' => isset($meta["seconds_{$lang}"]) ? (int) $meta["seconds_{$lang}"] : null,
                ];
            }

            return [
                'key' => $key,
                'n' => $i + 1,
                'title_en' => $meta['title_en'] ?? ucfirst(str_replace('-', ' ', preg_replace('/^\d+-/', '', $key))),
                'title_ar' => $meta['title_ar'] ?? null,
                'desc_en' => $meta['desc_en'] ?? null,
                'desc_ar' => $meta['desc_ar'] ?? null,
                'role' => $meta['role'] ?? null,
                'steps_en' => $meta['steps_en'] ?? [],
                'steps_ar' => $meta['steps_ar'] ?? [],
                'versions' => $versions,
            ];
        }, $keys, array_keys($keys)));
    }
}
