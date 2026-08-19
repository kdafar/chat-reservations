<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

/**
 * Designates a stock hub for every clinic group.
 *
 * StockTransferService::hubBranchId() looks the hub up per partner, so without
 * one the inter-branch transfer flow has no default source and every dispatch
 * fails with "No source branch". The flagship branch (the group's first) plays
 * the hub: it keeps seeing patients, it just also holds the central store.
 *
 * Re-runnable: leaves a group alone once it already has a hub.
 */
class StockHubSeeder extends Seeder
{
    public function run(): void
    {
        $branches = Branch::query()->withoutGlobalScopes()->orderBy('id')->get();

        if ($branches->isEmpty()) {
            $this->command?->warn('No branches — nothing to flag as a hub.');

            return;
        }

        foreach ($branches->groupBy('partner_id') as $partnerId => $group) {
            $existing = $group->firstWhere('is_hub', true);
            if ($existing) {
                $this->command?->line("  clinic #{$partnerId}: hub already set → ".$this->name($existing));

                continue;
            }

            $hub = $group->first();
            $hub->is_hub = true;
            $hub->save();

            $this->command?->info("  clinic #{$partnerId}: hub → ".$this->name($hub));
        }
    }

    private function name(Branch $b): string
    {
        $name = $b->getRawOriginal('name');
        $decoded = is_string($name) ? json_decode($name, true) : $name;

        return '#'.$b->id.' '.(is_array($decoded) ? ($decoded['en'] ?? reset($decoded)) : (string) $name);
    }
}
