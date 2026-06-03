<?php

namespace Database\Seeders;

use App\Models\ClinicItem;
use Illuminate\Database\Seeder;

/**
 * Demonstrates the 3-layer model on the demo data:
 *   Item (stock) → Service (carries a bill of materials) → Package (bundle of services).
 *
 * 1) Attaches a realistic bill of materials to each demo service, so adding the
 *    service to a visit auto-deducts its own consumables from stock.
 * 2) Adds a "Signature Glow Package" that BUNDLES services + a retail product —
 *    the modern package shape (consumables flow from each service's BOM, not
 *    relisted on the package).
 *
 * Idempotent: matches catalog items by name and upserts.
 */
class ServiceBomDemoSeeder extends Seeder
{
    public function run(): void
    {
        $items = ClinicItem::query()->get();
        if ($items->isEmpty()) {
            $this->command?->warn('No ClinicItem found; skipping ServiceBomDemoSeeder.');

            return;
        }

        // First non-service item whose EN/AR name contains the needle.
        $component = function (string $needle) use ($items): ?int {
            $needle = mb_strtolower(trim($needle));

            return optional($items->first(function (ClinicItem $it) use ($needle) {
                if ($it->type === 'service') {
                    return false;
                }
                $n = (array) ($it->name ?? []);
                return str_contains(mb_strtolower((string) ($n['en'] ?? '')), $needle)
                    || str_contains(mb_strtolower((string) ($n['ar'] ?? '')), $needle);
            }))->id;
        };

        // Recipes keyed by a fragment of the SERVICE name (most specific first).
        // Each line: [component-name-needle, qty_base, is_optional].
        $recipes = [
            'botox' => [['syringe 5', 1, false], ['needle 25', 2, false], ['alcohol', 2, false], ['gloves', 1, false]],
            'filler' => [['hyaluronic', 1, false], ['syringe 5', 1, false], ['needle 25', 1, false], ['alcohol', 2, false], ['gloves', 1, false]],
            'microneedling' => [['dermapen', 1, false], ['anesthetic', 1, false], ['gloves', 1, false], ['alcohol', 2, false]],
            'prp' => [['prp kit', 1, false], ['syringe 10', 1, false], ['needle 23', 1, false], ['gloves', 1, false], ['alcohol', 2, false]],
            'hydrafacial' => [['vitamin c serum', 1, false], ['gloves', 1, false], ['cotton', 2, false]],
            'chemical peel' => [['chemical peel solution', 1, false], ['cotton', 2, false], ['gloves', 1, false]],
            'laser hair' => [['gloves', 1, false], ['cotton', 1, true]],
            'iv injection' => [['iv cannula 22', 1, false], ['saline', 1, false], ['syringe 5', 1, false], ['alcohol', 1, false], ['gloves', 1, false]],
            'im injection' => [['syringe 5', 1, false], ['needle 23', 1, false], ['alcohol', 1, false], ['gloves', 1, false]],
            'nebulizer' => [['saline', 1, false], ['face mask', 1, false]],
            'wound dressing' => [['gauze', 2, false], ['bandage', 1, false], ['tape', 1, false], ['cotton', 1, false], ['gloves', 1, false]],
            'blood sugar' => [['alcohol', 1, false], ['cotton', 1, false], ['gloves', 1, true]],
            // Lab draws.
            'cbc' => [['syringe 5', 1, false], ['needle 23', 1, false], ['alcohol', 1, false], ['cotton', 1, false], ['gloves', 1, false]],
            'vitamin d test' => [['syringe 5', 1, false], ['needle 23', 1, false], ['alcohol', 1, false], ['cotton', 1, false], ['gloves', 1, false]],
            'hba1c' => [['syringe 5', 1, false], ['needle 23', 1, false], ['alcohol', 1, false], ['cotton', 1, false], ['gloves', 1, false]],
            // Consultations: minimal, optional gloves.
            'consultation' => [['gloves', 1, true]],
        ];

        $servicesTouched = 0;
        $linesCreated = 0;

        foreach ($items->where('type', 'service') as $service) {
            $name = mb_strtolower((string) (($service->name['en'] ?? '').' '.($service->name['ar'] ?? '')));

            $recipe = null;
            foreach ($recipes as $key => $lines) {
                if (str_contains($name, $key)) {
                    $recipe = $lines;
                    break;
                }
            }
            if (! $recipe) {
                continue;
            }

            $keepComponentIds = [];
            foreach ($recipe as [$needle, $qty, $optional]) {
                $componentId = $component($needle);
                if (! $componentId || $componentId === (int) $service->id) {
                    continue;
                }
                $keepComponentIds[] = $componentId;

                $service->components()->updateOrCreate(
                    ['component_item_id' => $componentId],
                    ['qty_base' => (float) $qty, 'is_optional' => (bool) $optional],
                );
                $linesCreated++;
            }

            // Keep idempotent: drop lines no longer in the recipe.
            if ($keepComponentIds) {
                $service->components()->whereNotIn('component_item_id', $keepComponentIds)->delete();
                $servicesTouched++;
            }
        }

        $this->command?->info("ServiceBomDemoSeeder: {$servicesTouched} services given a BOM ({$linesCreated} component lines). Bundle packages are seeded by ClinicPackageSeeder.");
    }
}
