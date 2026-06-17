<?php

namespace Database\Seeders;

use App\Models\Accounting\PostingAccountMap;
use Illuminate\Database\Seeder;

/**
 * Seeds one posting-map row per role with account_id = NULL (use default).
 * Idempotent: re-running keeps any overrides the accountant has set.
 */
class PostingAccountMapSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PostingAccountMap::DEFAULTS as $role => $code) {
            PostingAccountMap::firstOrCreate(
                ['role' => $role],
                ['default_code' => $code, 'account_id' => null],
            );
            // Keep the stored default_code current even if a role's default changes.
            PostingAccountMap::where('role', $role)->update(['default_code' => $code]);
        }

        $this->command?->info('Seeded '.count(PostingAccountMap::DEFAULTS).' posting-account roles.');
    }
}
