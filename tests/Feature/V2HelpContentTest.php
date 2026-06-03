<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Drift guard for the v2 "How to use this page" help system.
 *
 * Keeps three sources in lockstep so a new v2 page can never silently ship
 * without bilingual help (or with EN-only content):
 *   - resources/lang/en/help_v2.php   (English content)
 *   - resources/lang/ar/help_v2.php   (Arabic content)
 *   - resources/js/v2/helpMap.js      (HELP_PAGES set → controls the Help button)
 *
 * The controller (App\Http\Controllers\V2\HelpController) reads help_v2.pages.{navId}
 * directly, and helpMap.js decides whether the button renders, so these must match.
 */
class V2HelpContentTest extends TestCase
{
    private function enPages(): array
    {
        return require base_path('resources/lang/en/help_v2.php');
    }

    private function arPages(): array
    {
        return require base_path('resources/lang/ar/help_v2.php');
    }

    /** Parse the nav-ids listed inside `new Set([...])` in helpMap.js. */
    private function jsHelpKeys(): array
    {
        $js = file_get_contents(base_path('resources/js/v2/helpMap.js'));
        $this->assertNotFalse($js, 'helpMap.js is missing');

        // Isolate the Set literal so we never match stray quotes elsewhere.
        $this->assertSame(1, preg_match('/new Set\(\[(.*?)\]\)/s', $js, $m), 'Could not find HELP_PAGES Set in helpMap.js');
        preg_match_all("/'([a-z0-9-]+)'/", $m[1], $keys);

        return array_values(array_unique($keys[1]));
    }

    public function test_en_and_ar_have_identical_page_keys(): void
    {
        $en = array_keys($this->enPages()['pages']);
        $ar = array_keys($this->arPages()['pages']);
        sort($en);
        sort($ar);

        $this->assertSame(
            $en,
            $ar,
            'EN/AR help_v2 page keys diverged. EN-only: ['.implode(', ', array_diff($en, $ar)).'] AR-only: ['.implode(', ', array_diff($ar, $en)).']',
        );
    }

    public function test_help_map_js_matches_php_keys(): void
    {
        $php = array_keys($this->enPages()['pages']);
        $js = $this->jsHelpKeys();
        sort($php);
        sort($js);

        $this->assertSame(
            $php,
            $js,
            'helpMap.js drifted from help_v2.php. In PHP only (button hidden despite content): ['.implode(', ', array_diff($php, $js)).'] In JS only (button shows but 404s): ['.implode(', ', array_diff($js, $php)).']',
        );
    }

    public function test_modal_strings_exist_in_both_locales(): void
    {
        foreach (['en' => $this->enPages(), 'ar' => $this->arPages()] as $loc => $data) {
            $this->assertArrayHasKey('modal', $data, "[$loc] missing 'modal'");
            $this->assertStringContainsString(':page', $data['modal']['heading'] ?? '', "[$loc] modal.heading must contain the :page placeholder");
            $this->assertNotEmpty($data['modal']['description'] ?? '', "[$loc] modal.description is empty");
        }
    }

    public function test_every_page_has_complete_sections_in_both_locales(): void
    {
        foreach (['en' => $this->enPages(), 'ar' => $this->arPages()] as $loc => $data) {
            foreach ($data['pages'] as $key => $page) {
                // what — paragraph
                $this->assertNotEmpty($page['what']['heading'] ?? '', "[$loc:$key] what.heading is empty");
                $this->assertNotEmpty($page['what']['body'] ?? '', "[$loc:$key] what.body is empty");

                // how — bullet list
                $this->assertNotEmpty($page['how']['heading'] ?? '', "[$loc:$key] how.heading is empty");
                $items = $page['how']['items'] ?? [];
                $this->assertIsArray($items, "[$loc:$key] how.items is not an array");
                $this->assertNotEmpty($items, "[$loc:$key] how.items is empty");
                foreach ($items as $i => $bullet) {
                    $this->assertIsString($bullet, "[$loc:$key] how.items[$i] is not a string");
                    $this->assertNotEmpty(trim($bullet), "[$loc:$key] how.items[$i] is blank");
                }

                // faq — q/a pairs
                $this->assertNotEmpty($page['faq']['heading'] ?? '', "[$loc:$key] faq.heading is empty");
                $faq = $page['faq']['items'] ?? [];
                $this->assertIsArray($faq, "[$loc:$key] faq.items is not an array");
                $this->assertNotEmpty($faq, "[$loc:$key] faq.items is empty");
                foreach ($faq as $i => $qa) {
                    $this->assertNotEmpty($qa['q'] ?? '', "[$loc:$key] faq.items[$i].q is empty");
                    $this->assertNotEmpty($qa['a'] ?? '', "[$loc:$key] faq.items[$i].a is empty");
                }
            }
        }
    }
}
