<?php

namespace App\Console\Commands;

use App\Models\ClinicItem;
use App\Models\ClinicPackage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off remediation for the clinic-owned-catalog migration:
 *   1. Backfill partner_id on clinic_items / clinic_packages from their branch's
 *      partner (branch_id null rows stay partner_id null = platform-global).
 *   2. Repair cross-clinic references: a package whose component item belongs to a
 *      DIFFERENT clinic gets re-pointed to a same-named item in the package's
 *      clinic, cloning the item (and its BOM, recursively) into that clinic when
 *      none exists. Idempotent; supports --dry-run.
 */
class BackfillCatalogPartner extends Command
{
    protected $signature = 'catalog:backfill-partner {--dry-run}';

    protected $description = 'Backfill partner_id on clinic catalog + repair cross-clinic package/BOM references';

    private bool $dry = false;

    private array $log = [];

    public function handle(): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $this->info(($this->dry ? '[DRY RUN] ' : '').'Clinic catalog partner backfill + remediation');

        $work = function () {
            $this->backfill('clinic_items');
            $this->backfill('clinic_packages');
            $this->remediatePackages();
            $this->report();
        };

        // Apply atomically so a mid-run failure never leaves a half-remediated catalog.
        $this->dry ? $work() : DB::transaction($work);

        return self::SUCCESS;
    }

    private function backfill(string $table): void
    {
        $rows = DB::table($table.' as t')
            ->join('branches as b', 'b.id', '=', 't.branch_id')
            ->whereNull('t.partner_id')
            ->whereNotNull('t.branch_id')
            ->whereNotNull('b.partner_id')
            ->select('t.id', 'b.partner_id')
            ->get();

        foreach ($rows as $r) {
            $this->log[] = "backfill {$table}#{$r->id} -> partner {$r->partner_id}";
            if (! $this->dry) {
                DB::table($table)->where('id', $r->id)->update(['partner_id' => $r->partner_id, 'updated_at' => now()]);
            }
        }
        $this->line("  {$table}: backfilled ".count($rows).' rows');
    }

    /**
     * Ensure an equivalent of $srcId exists in $partnerId; return its id.
     * Global (partner_id null) or already-in-partner items are returned as-is.
     * Otherwise reuse a same-named partner item, or clone (incl. BOM).
     */
    private function ensureItemInPartner(int $srcId, int $partnerId, array &$cache): ?int
    {
        $key = $srcId.'@'.$partnerId;
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $src = ClinicItem::withoutGlobalScopes()->find($srcId);
        if (! $src) {
            return $cache[$key] = null;
        }
        if ($src->partner_id === null || (int) $src->partner_id === $partnerId) {
            return $cache[$key] = $srcId;
        }

        $nameEn = is_array($src->name) ? ($src->name['en'] ?? null) : null;
        $existing = ClinicItem::withoutGlobalScopes()
            ->where('partner_id', $partnerId)
            ->when($nameEn, fn ($q) => $q->where('name->en', $nameEn))
            ->first();
        if ($existing) {
            return $cache[$key] = (int) $existing->id;
        }

        $this->log[] = "clone item#{$srcId} ('".($nameEn ?? '?')."') -> partner {$partnerId}";
        if ($this->dry) {
            return $cache[$key] = 0; // id unknown until applied
        }

        $new = $src->replicate();
        $new->partner_id = $partnerId;
        $new->branch_id = null;
        $new->save();
        $cache[$key] = (int) $new->id;

        // Clone the BOM, re-pointing each component into the same partner.
        foreach (DB::table('clinic_item_components')->where('service_item_id', $srcId)->get() as $c) {
            $compTarget = $this->ensureItemInPartner((int) $c->component_item_id, $partnerId, $cache);
            if (! $compTarget) {
                continue;
            }
            DB::table('clinic_item_components')->insert([
                'service_item_id' => $new->id,
                'component_item_id' => $compTarget,
                'qty_base' => $c->qty_base,
                'is_optional' => $c->is_optional,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $cache[$key];
    }

    private function remediatePackages(): void
    {
        $cache = [];
        $repointed = 0;
        $globalPkgWarnings = 0;

        foreach (ClinicPackage::withoutGlobalScopes()->get() as $pkg) {
            $pPartner = $pkg->partner_id;
            $items = DB::table('clinic_package_items')->where('clinic_package_id', $pkg->id)->get();

            foreach ($items as $pi) {
                $item = ClinicItem::withoutGlobalScopes()->find($pi->clinic_item_id);
                if (! $item) {
                    continue;
                }
                $iPartner = $item->partner_id;

                if ($pPartner === null) {
                    // Global package should only hold global items — flag, don't guess a clinic.
                    if ($iPartner !== null) {
                        $globalPkgWarnings++;
                        $this->log[] = "WARN global pkg#{$pkg->id} holds partner item#{$item->id} (left as-is)";
                    }
                    continue;
                }

                if ($iPartner === null || (int) $iPartner === (int) $pPartner) {
                    continue; // ok
                }

                $targetId = $this->ensureItemInPartner((int) $pi->clinic_item_id, (int) $pPartner, $cache);
                $this->log[] = "repoint pkg#{$pkg->id} item#{$pi->clinic_item_id} -> #{$targetId} (partner {$pPartner})";
                $repointed++;
                if (! $this->dry && $targetId) {
                    DB::table('clinic_package_items')->where('id', $pi->id)->update(['clinic_item_id' => $targetId, 'updated_at' => now()]);
                }
            }
        }

        $this->line("  packages: re-pointed {$repointed} component refs".($globalPkgWarnings ? ", {$globalPkgWarnings} global-package warnings" : ''));
    }

    private function report(): void
    {
        $bad = 0;
        foreach (ClinicPackage::withoutGlobalScopes()->get() as $pkg) {
            if ($pkg->partner_id === null) {
                continue;
            }
            foreach (DB::table('clinic_package_items')->where('clinic_package_id', $pkg->id)->pluck('clinic_item_id') as $iid) {
                $it = ClinicItem::withoutGlobalScopes()->find($iid);
                if (! $it) {
                    $bad++;
                    continue;
                }
                if ($it->partner_id !== null && (int) $it->partner_id !== (int) $pkg->partner_id) {
                    $bad++;
                }
            }
        }

        $this->newLine();
        if ($this->dry) {
            $this->warn('DRY RUN — planned actions:');
            foreach ($this->log as $l) {
                $this->line('  '.$l);
            }
            $this->info("Cross-clinic package mismatches BEFORE fix: {$bad}");
        } else {
            $this->info("Remaining cross-clinic package mismatches: {$bad}");
        }
    }
}
