<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for the "link shows but the page 403s" bug class.
 *
 * The v2 sidebar (resources/js/v2/Layouts/AppLayout.vue) gates every link via a
 * `navGates` map keyed by item id. `itemVisible()` returns TRUE for any item id
 * that is MISSING from navGates — so an ungated link defaults to visible for
 * EVERYONE, even roles that 403 on the destination. We hit exactly this with
 * `wap-media` / `wap-points` (the whole WhatsApp Platform section stayed visible
 * for an accountant who can't open it).
 *
 * This DB-less test parses the SFC source and fails if:
 *   1. any linked sidebar item has no navGate entry, or
 *   2. any navGate entry declares none of perm/roles/flags (an empty gate is
 *      effectively "deny", almost certainly a mistake).
 *
 * Keeping the sidebar permission-driven is only safe if every new link is
 * forced to declare a gate — that's what this enforces.
 */
class SidebarNavGateTest extends TestCase
{
    private function source(): string
    {
        return file_get_contents(dirname(__DIR__, 2).'/resources/js/v2/Layouts/AppLayout.vue');
    }

    private function slice(string $s, string $from, string $to): string
    {
        $a = strpos($s, $from);
        $this->assertNotFalse($a, "Could not find '{$from}' in AppLayout.vue (did the file change?)");
        $b = strpos($s, $to, $a);
        $this->assertNotFalse($b, "Could not find '{$to}' after '{$from}' in AppLayout.vue");

        return substr($s, $a, $b - $a);
    }

    /** The keys declared in the navGates map. */
    private function gateKeys(string $src): array
    {
        $block = $this->slice($src, 'const navGates', 'function itemVisible');
        preg_match_all("/^\\s*'?([a-z0-9-]+)'?\\s*:\\s*\\{/m", $block, $m);

        return array_flip($m[1]);
    }

    public function test_every_linked_sidebar_item_declares_a_nav_gate(): void
    {
        $src = $this->source();
        $gateKeys = $this->gateKeys($src);

        // Only inspect the navSections definition (the link tree), not template
        // hrefs elsewhere. Leaf items are flat brace-objects (no nesting) that
        // carry an `href:`; section wrappers contain nested `items: [ {...} ]`
        // and so are excluded by the no-inner-brace pattern below.
        $nav = $this->slice($src, 'const navSections', '].map(section');

        preg_match_all('/\{[^{}]*\}/', $nav, $objs);
        $missing = [];
        foreach ($objs[0] as $obj) {
            if (! str_contains($obj, 'href:')) {
                continue;
            }
            if (! preg_match("/id:\\s*'([a-z0-9-]+)'/", $obj, $idm)) {
                continue;
            }
            if (! isset($gateKeys[$idm[1]])) {
                $missing[] = $idm[1];
            }
        }

        $missing = array_values(array_unique($missing));
        $this->assertSame(
            [],
            $missing,
            "Sidebar links with NO navGate (they default to visible for EVERYONE). "
            ."Add a gate in navGates — '{ perm: '...' }' preferred so it follows the role/permission layer: "
            .implode(', ', $missing)
        );
    }

    public function test_no_nav_gate_is_empty(): void
    {
        $src = $this->source();
        $block = $this->slice($src, 'const navGates', 'function itemVisible');

        preg_match_all("/^\\s*'?([a-z0-9-]+)'?\\s*:\\s*\\{([^}]*)\\}/m", $block, $m, PREG_SET_ORDER);
        $empty = [];
        foreach ($m as $entry) {
            [$_, $key, $body] = $entry;
            if (! preg_match('/\b(perm|roles|flags)\s*:/', $body)) {
                $empty[] = $key;
            }
        }

        $this->assertSame(
            [],
            $empty,
            'navGate entries declaring none of perm/roles/flags (effectively deny — likely a mistake): '
            .implode(', ', $empty)
        );
    }
}
