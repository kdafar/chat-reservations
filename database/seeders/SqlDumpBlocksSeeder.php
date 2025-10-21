<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SqlDumpBlocksSeeder extends Seeder
{
    private const STATE_SQL = 'database/seeders/data/state.sql';

    private const CITY_SQL = 'database/seeders/data/city.sql';

    private const BLOCK_SQL = 'database/seeders/data/blocks.sql';

    /** Hard reset before import (recommended the first time) */
    private const RESET_BLOCKS = true;

    /** Create the city in your new DB if it’s missing (based on legacy name/state) */
    private const CREATE_MISSING_CITIES = true;

    /** Keep only the 6 governorates; anything else in legacy state is ignored */
    private const STRICT_SIX_GOVS = true;

    /** Canonical governorates → [slug, en, ar] */
    private const CANON_STATES = [
        'assima' => ['capital', 'Capital', 'العاصمة'],
        'al asimah' => ['capital', 'Capital', 'العاصمة'],
        'al-asimah' => ['capital', 'Capital', 'العاصمة'],
        'capital' => ['capital', 'Capital', 'العاصمة'],
        'ahmadi' => ['ahmadi', 'Ahmadi', 'الأحمدي'],
        'hawalli' => ['hawalli', 'Hawalli', 'حولي'],
        'farwaniya' => ['farwaniya', 'Farwaniya', 'الفروانية'],
        'jahra' => ['jahra', 'Al Jahra', 'الجهراء'],
        'mubarak al kabeer' => ['mubarak-al-kabeer', 'Mubarak Al-Kabeer', 'مبارك الكبير'],
        'mubarak al kabir' => ['mubarak-al-kabeer', 'Mubarak Al-Kabeer', 'مبارك الكبير'],
        'mubarak al-kabeer' => ['mubarak-al-kabeer', 'Mubarak Al-Kabeer', 'مبارك الكبير'],
    ];

    public function run(): void
    {
        $this->command->info('Blocks import: preparing legacy temp tables…');

        // 0) Optional reset
        if (self::RESET_BLOCKS) {
            Schema::disableForeignKeyConstraints();
            if (Schema::hasTable('blocks')) {
                DB::table('blocks')->truncate();
            }
            Schema::enableForeignKeyConstraints();
            $this->command->warn('RESET: truncated blocks table.');
        }

        // 1) Load legacy tables (state, city, blocks) into temp tables
        $stateSql = $this->prepareSql(self::STATE_SQL, 'state', 'legacy_state');
        $citySql = $this->prepareSql(self::CITY_SQL, 'city', 'legacy_city');
        $blockSql = $this->prepareSql(self::BLOCK_SQL, 'blocks', 'legacy_blocks');

        Schema::dropIfExists('legacy_blocks');
        Schema::dropIfExists('legacy_city');
        Schema::dropIfExists('legacy_state');

        DB::unprepared($stateSql);
        DB::unprepared($citySql);
        DB::unprepared($blockSql);

        // 2) Build state map (legacy -> new)
        $legacyStates = DB::table('legacy_state')->orderBy('id')->get();
        $legacyStateToNew = [];
        foreach ($legacyStates as $r) {
            $canon = $this->canonicalizeState((string) $r->state_name, (string) $r->state_name_ar);
            if (! $canon) {
                if (self::STRICT_SIX_GOVS) {
                    continue;
                }
                $slug = Str::slug($r->state_name ?: $r->state_name_ar);
            } else {
                [$slug] = $canon;
            }
            if ($slug === '' || $slug === null) {
                continue;
            }

            $state = State::query()->where('slug', $slug)->first();
            if ($state) {
                $legacyStateToNew[(int) $r->id] = $state->id;
            }
        }

        // 3) Build city map (legacy -> new), optionally create missing
        $legacyCityToNew = [];
        DB::table('legacy_city')->orderBy('id')->chunkById(1000, function ($chunk) use (&$legacyCityToNew, $legacyStateToNew) {
            foreach ($chunk as $r) {
                $legacyStateId = (int) $r->state_id;
                $newStateId = $legacyStateToNew[$legacyStateId] ?? null;
                if (! $newStateId) {
                    continue;
                }

                $en = trim((string) $r->city_name);
                $ar = trim((string) $r->city_name_ar);
                $normEn = Str::lower(preg_replace('/\s+/', ' ', $en));

                // try exact match in new DB by name (EN or AR) within that state
                $match = City::query()
                    ->where('state_id', $newStateId)
                    ->where(function ($q) use ($normEn, $ar) {
                        if ($normEn !== '') {
                            $q->orWhereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.en'))) = ?", [$normEn]);
                        }
                        if ($ar !== '') {
                            $q->orWhere('name->ar', $ar);
                        }
                    })
                    ->first();

                if (! $match && self::CREATE_MISSING_CITIES) {
                    $slugBase = Str::slug($en ?: $ar) ?: ('city-'.$r->id);
                    $slug = $this->uniqueCitySlugGlobal($slugBase);

                    $match = City::create([
                        'state_id' => $newStateId,
                        'slug' => $slug,
                        'name' => ['en' => $en !== '' ? $en : $slug, 'ar' => $ar !== '' ? $ar : ($en !== '' ? $en : $slug)],
                        'is_active' => 1,
                    ]);
                    $this->command->line("• Created missing city: {$slug} (state_id={$newStateId})");
                }

                if ($match) {
                    $legacyCityToNew[(int) $r->id] = $match->id;
                }
            }
        });

        // 4) Import blocks
        $created = $updated = $skipped = 0;

        DB::table('legacy_blocks')->orderBy('id')->chunkById(1000, function ($chunk) use (&$created, &$updated, &$skipped, $legacyCityToNew) {
            foreach ($chunk as $r) {
                $legacyCityId = (int) $r->city_id;
                $newCityId = $legacyCityToNew[$legacyCityId] ?? null;
                if (! $newCityId) {
                    $skipped++;

                    continue;
                }

                $code = trim((string) $r->code);
                if ($code === '') {
                    // derive from name like "Block 3" → 3
                    $derived = $this->deriveCodeFromNames((string) $r->name_en, (string) $r->name_ar);
                    $code = $derived ?: ('blk-'.$r->id);
                }

                $nameEn = trim((string) $r->name_en);
                $nameAr = trim((string) $r->name_ar);
                if ($nameEn === '' && preg_match('/\d+/', $code, $m)) {
                    $nameEn = 'Block '.$m[0];
                }
                if ($nameAr === '' && preg_match('/\d+/', $code, $m)) {
                    $nameAr = 'قطعة '.$m[0];
                }

                $lat = is_null($r->lat) ? null : (float) $r->lat;
                $lng = is_null($r->lng) ? null : (float) $r->lng;

                // Upsert by (city_id, code)
                $existing = Block::query()
                    ->where('city_id', $newCityId)
                    ->where('code', $code)
                    ->first();

                if ($existing) {
                    $existing->city_id = $newCityId;
                    $existing->setTranslations('name', ['en' => $nameEn ?: $existing->getTranslation('name', 'en'), 'ar' => $nameAr ?: $existing->getTranslation('name', 'ar')]);
                    $existing->latitude = $lat;
                    $existing->longitude = $lng;
                    $existing->is_active = 1;
                    $existing->save();
                    $updated++;

                    continue;
                }

                Block::create([
                    'city_id' => $newCityId,
                    'code' => $code,
                    'name' => ['en' => $nameEn !== '' ? $nameEn : $code, 'ar' => $nameAr !== '' ? $nameAr : ($nameEn !== '' ? $nameEn : $code)],
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'is_active' => 1,
                ]);
                $created++;
            }
        });

        $this->command->info("Blocks → created {$created}, updated {$updated}, skipped {$skipped}");

        // 5) Cleanup
        Schema::dropIfExists('legacy_blocks');
        Schema::dropIfExists('legacy_city');
        Schema::dropIfExists('legacy_state');

        $this->command->info('SqlDumpBlocksSeeder completed.');
    }

    private function deriveCodeFromNames(string $en, string $ar): ?string
    {
        if (preg_match('/\b(\d{1,3})\b/', $en, $m)) {
            return $m[1];
        }
        if (preg_match('/\b(\d{1,3})\b/u', $ar, $m)) {
            return $m[1];
        }

        return null;
    }

    private function uniqueCitySlugGlobal(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (City::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            if (++$i > 100) {
                break;
            }
        }

        return $slug;
    }

    private function canonicalizeState(string $en, string $ar): ?array
    {
        $keyEn = Str::lower(preg_replace('/\s+/', ' ', $en));
        if (isset(self::CANON_STATES[$keyEn])) {
            return self::CANON_STATES[$keyEn];
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
