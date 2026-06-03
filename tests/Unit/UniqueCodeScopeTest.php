<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Regression guard for a bug class we hit twice (admission_code, booking_code):
 * a GLOBALLY-unique code generated with a scope-affected uniqueness check.
 *
 * Models like Booking/Admission carry BelongsToBranchScope, so a plain
 * `Model::where('<code>', $candidate)->exists()` (or count()/max-via-value)
 * only checks the current user's branch. A non-admin can then "approve" a code
 * already used in another branch and trip the global unique index on insert.
 *
 * Any uniqueness/sequence check on these columns MUST call withoutGlobalScopes().
 * This test scans the source (no DB) and fails listing offenders — including
 * any NEW generator someone adds later.
 */
class UniqueCodeScopeTest extends TestCase
{
    /** Code columns that have a GLOBAL unique index. */
    private array $globalUniqueCodeColumns = ['booking_code', 'admission_code'];

    public function test_unique_code_generators_bypass_global_scopes(): void
    {
        $offenders = [];

        foreach ($this->phpFiles(dirname(__DIR__, 2).'/app') as $file) {
            $src = file_get_contents($file);

            foreach ($this->globalUniqueCodeColumns as $col) {
                if (! preg_match_all("/where\\(\\s*['\"]{$col}['\"]/", $src, $m, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($m[0] as [$_, $offset]) {
                    // Look at the statement around the where() call.
                    $window = substr($src, max(0, $offset - 220), 460);

                    // Is this a uniqueness/sequence check (vs. a plain scoped read)?
                    $isGenerator = str_contains($window, '->exists()')
                        || str_contains($window, '->count(')
                        || (str_contains($window, '->value(') && str_contains($window, 'orderByDesc'));

                    if ($isGenerator && ! str_contains($window, 'withoutGlobalScopes')) {
                        $offenders[] = basename($file).' — '.$col;
                    }
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Uniqueness/sequence checks on globally-unique code columns must use ".
            "withoutGlobalScopes() (else they only check the current branch):\n  ".
            implode("\n  ", $offenders)
        );
    }

    /** @return list<string> */
    private function phpFiles(string $dir): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[] = $f->getPathname();
            }
        }

        return $out;
    }
}
