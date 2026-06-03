<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dedicated "How to use this page" help for the v2 admin UI.
 *
 * Content lives in resources/lang/{en,ar}/help_v2.php — written specifically
 * against the v2 pages (real buttons/flows), and kept SEPARATE from the legacy
 * resources/lang/{en,ar}/help.php used by the old Filament panel. Keys are the
 * v2 nav-item ids (see helpMap.js, which mirrors the available keys so the Help
 * button only renders where content exists).
 *
 * This controller just reads the localised content for a key and normalises it
 * into a flat section list the HelpDrawer.vue component renders.
 */
class HelpController extends Controller
{
    public function show(Request $request, string $key): JsonResponse
    {
        $page = trans("help_v2.pages.{$key}");

        if (! is_array($page)) {
            return response()->json(['available' => false], 404);
        }

        $sections = [];
        foreach ($page as $block) {
            if (! is_array($block)) {
                continue;
            }

            $section = ['heading' => $block['heading'] ?? null];

            if (! empty($block['body'])) {
                $section['body'] = $block['body'];
            } elseif (! empty($block['items']) && is_array($block['items'])) {
                $first = $block['items'][0] ?? null;
                if (is_array($first) && isset($first['q'])) {
                    $section['faq'] = array_values(array_map(
                        fn ($qa) => ['q' => $qa['q'] ?? '', 'a' => $qa['a'] ?? ''],
                        $block['items'],
                    ));
                } else {
                    $section['items'] = array_values($block['items']);
                }
            }

            if (isset($section['body']) || isset($section['items']) || isset($section['faq'])) {
                $sections[] = $section;
            }
        }

        // The page label is supplied by the front-end (it owns the nav labels,
        // already localised); fall back to a humanised key if absent.
        $title = trim((string) $request->query('title', ''));
        if ($title === '') {
            $title = ucwords(str_replace(['-', '_'], ' ', $key));
        }

        return response()->json([
            'available'   => true,
            'heading'     => __('help_v2.modal.heading', ['page' => $title]),
            'description' => __('help_v2.modal.description'),
            'sections'    => $sections,
        ]);
    }
}
