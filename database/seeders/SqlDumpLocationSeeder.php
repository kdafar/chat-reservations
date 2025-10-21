<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SqlDumpLocationSeeder extends Seeder
{
    private const STATE_SQL = 'database/seeders/data/state.sql';

    private const CITY_SQL = 'database/seeders/data/city.sql';

    /** Hard reset before importing (deletes all rows to avoid dup conflicts) */
    private const RESET_STATES_AND_CITIES = true;

    /** If blocks depend on cities and you want a full clean start, flip to true */
    private const ALSO_RESET_BLOCKS = false;

    private const STRICT_SIX_GOVS = true;

    private const CANON = [
        'assima' => ['capital', 'Capital', 'العاصمة'],
        'al asimah' => ['capital', 'Capital', 'العاصمة'],
        'al-asimah' => ['capital', 'Capital', 'العاصمة'],
        'capital' => ['capital', 'Capital', 'العاصمة'],
        'ahmadi' => ['ahmadi', 'Ahmadi', 'الأحمدي'],
        'hawalli' => ['hawalli', 'Hawalli', 'حولي'],
        'farwaniya' => ['farwaniya', 'Farwaniya', 'الفروانية'],
        'jahra' => ['jahra', 'Al Jahra', 'الجهراء'],
        'mubarak al kabeer' => ['mubarak-al-kabeer', 'Mubarak Al-Kabeer', 'مبارك الكبير'],
        'mubarak al-kabeer' => ['mubarak-al-kabeer', 'Mubarak Al-Kabeer', 'مبارك الكبير'],
        'mubarak al kabir' => ['mubarak-al-kabeer', 'Mubarak Al-Kabeer', 'مبارك الكبير'],
    ];

    public function run(): void
    {
        $this->command->info('Loading SQL dumps into temporary tables…');

        // 0) PRE-RESET (optional)
        if (self::RESET_STATES_AND_CITIES) {
            $this->command->warn('RESET mode: deleting old states & cities (and optionally blocks)…');
            Schema::disableForeignKeyConstraints();
            if (self::ALSO_RESET_BLOCKS && Schema::hasTable('blocks')) {
                DB::table('blocks')->truncate();
            }
            if (Schema::hasTable('cities')) {
                DB::table('cities')->truncate();
            }
            if (Schema::hasTable('states')) {
                DB::table('states')->truncate();
            }
            Schema::enableForeignKeyConstraints();
        }

        // 1) Read & retarget SQL into legacy_* temp tables
        $stateSql = $this->prepareSql(self::STATE_SQL, 'state', 'legacy_state');
        $citySql = $this->prepareSql(self::CITY_SQL, 'city', 'legacy_city');

        Schema::dropIfExists('legacy_city');
        Schema::dropIfExists('legacy_state');

        DB::unprepared($stateSql);
        DB::unprepared($citySql);

        // 2) STATES
        $legacyStates = DB::table('legacy_state')->orderBy('id')->get();
        $legacyToNewStateId = [];
        $createdS = $updatedS = $skippedS = 0;

        foreach ($legacyStates as $r) {
            $en = trim((string) $r->state_name);
            $ar = trim((string) $r->state_name_ar);

            $canon = $this->canonicalizeState($en, $ar);
            if (! $canon) {
                if (self::STRICT_SIX_GOVS) {
                    $skippedS++;

                    continue;
                }
                $canon = [Str::slug($en ?: $ar), $en ?: ($ar ?: 'State'), $ar ?: ($en ?: 'State')];
            }
            [$slug, $cen, $car] = $canon;

            $active = ((int) $r->is_available === 1) && ((int) $r->is_deleted !== 1);

            $state = State::query()->where('slug', $slug)->first();
            if ($state) {
                $state->setTranslations('name', ['en' => $cen, 'ar' => $car]);
                $state->is_active = $active;
                $state->save();
                $updatedS++;
            } else {
                $state = State::create([
                    'slug' => $slug,
                    'name' => ['en' => $cen, 'ar' => $car],
                    'is_active' => $active,
                ]);
                $createdS++;
            }

            $legacyToNewStateId[(int) $r->id] = $state->id;
        }

        $this->command->info("States → created {$createdS}, updated {$updatedS}, skipped {$skippedS}");

        // 3) CITIES
        $createdC = $updatedC = $skippedC = 0;

        DB::table('legacy_city')->orderBy('id')->chunkById(500, function ($chunk) use (&$createdC, &$updatedC, &$skippedC, $legacyToNewStateId) {
            foreach ($chunk as $r) {
                $stateId = $legacyToNewStateId[(int) $r->state_id] ?? null;
                if (! $stateId) {
                    $skippedC++;

                    continue;
                }

                $en = trim((string) $r->city_name);
                $ar = trim((string) $r->city_name_ar);

                // Base slug
                $baseSlug = Str::slug($en ?: $ar);
                if ($baseSlug === '') {
                    $baseSlug = 'city-'.(int) $r->id;
                }

                $active = ((int) $r->is_available === 1) && ((int) $r->is_deleted !== 1);

                // *** GLOBAL SLUG CHECK ***
                // if a city with same slug exists (any state), update it instead of inserting a duplicate
                $existingBySlug = City::query()->where('slug', $baseSlug)->first();

                if ($existingBySlug) {
                    // Move it to the new state if needed, update names & active
                    $existingBySlug->state_id = $stateId;
                    $existingBySlug->setTranslations('name', [
                        'en' => $en !== '' ? $en : $existingBySlug->getTranslation('name', 'en'),
                        'ar' => $ar !== '' ? $ar : $existingBySlug->getTranslation('name', 'ar'),
                    ]);
                    $existingBySlug->is_active = $active;
                    $existingBySlug->save();
                    $updatedC++;

                    continue;
                }

                // If slug must be unique globally, generate a unique one
                $slug = $this->uniqueCitySlugGlobal($baseSlug);

                // Create
                City::create([
                    'state_id' => $stateId,
                    'slug' => $slug,
                    'name' => ['en' => $en !== '' ? $en : $slug, 'ar' => $ar !== '' ? $ar : ($en !== '' ? $en : $slug)],
                    'is_active' => $active,
                ]);
                $createdC++;
            }
        });

        $this->command->info("Cities → created {$createdC}, updated {$updatedC}, skipped {$skippedC}");

        // 4) Cleanup temp tables
        Schema::dropIfExists('legacy_city');
        Schema::dropIfExists('legacy_state');

        $this->command->info('SqlDumpLocationSeeder completed.');
    }

    /** Ensure global uniqueness if your DB has a unique index on cities.slug */
    private function uniqueCitySlugGlobal(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (City::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
            if ($i > 100) {
                break;
            }
        }

        return $slug;
    }

    private function canonicalizeState(string $en, string $ar): ?array
    {
        $keyEn = Str::lower(preg_replace('/\s+/', ' ', $en));
        if (isset(self::CANON[$keyEn])) {
            return self::CANON[$keyEn];
        }

        $mapAr = [
            'الأحمدي' => ['ahmadi', 'Ahmadi', 'الأحمدي'],
            'الجهراء' => ['jahra', 'Al Jahra', 'الجهراء'],
            'حولي' => ['hawalli', 'Hawalli', 'حولي'],
            'العاصمة' => ['capital', 'Capital', 'العاصمة'],
            'الفروانية' => ['farwaniya', 'Farwaniya', 'الفروانية'],
            'مبارك الكبير' => ['mubarak-al-kabeer', 'Mubarak Al-Kabeer', 'مبارك الكبير'],
        ];

        return $mapAr[$ar] ?? (self::STRICT_SIX_GOVS ? null : [Str::slug($en ?: $ar), $en ?: ($ar ?: 'State'), $ar ?: ($en ?: 'State')]);
    }

    // prepareSql() and cleanSql() stay the same as your previous file…
    private function prepareSql(string $relPath, string $fromTable, string $toTable): string
    {
        $path = base_path($relPath);
        if (! File::exists($path)) {
            throw new \RuntimeException("SQL file missing: {$relPath}");
        }
        $sql = File::get($path);
        $sql = $this->cleanSql($sql);
        $patterns = [
            "/CREATE TABLE\s+`{$fromTable}`/i" => "CREATE TABLE `{$toTable}`",
            "/INSERT INTO\s+`{$fromTable}`/i" => "INSERT INTO `{$toTable}`",
            "/ALTER TABLE\s+`{$fromTable}`/i" => "ALTER TABLE `{$toTable}`",
            "/TABLE `{$fromTable}`/i" => "TABLE `{$toTable}`",
        ];

        return preg_replace(array_keys($patterns), array_values($patterns), $sql);
    }

    private function cleanSql(string $sql): string
    {
        $lines = preg_split("/\r\n|\n|\r/", $sql);
        $keep = [];
        foreach ($lines as $line) {
            $t = ltrim($line);
            if ($t === '' || str_starts_with($t, '--')) {
                continue;
            }
            if (str_starts_with($t, '/*')) {
                continue;
            }
            if (preg_match('/^\/\*!/', $t)) {
                continue;
            }
            if (stripos($t, 'SET ') === 0) {
                continue;
            }
            if (stripos($t, 'START TRANSACTION') === 0) {
                continue;
            }
            if (stripos($t, 'COMMIT') === 0) {
                continue;
            }
            $keep[] = $line;
        }

        return implode("\n", $keep);
    }
}
