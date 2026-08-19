<?php

namespace Database\Seeders;

use App\Models\ClinicItem;
use App\Models\ClinicItemComponent;
use Illuminate\Database\Seeder;

/**
 * Bills of materials for the aesthetic service catalogue: which consumables
 * each service burns when it is performed, so a completed visit actually moves
 * stock instead of leaving the inventory frozen at its opening balance.
 *
 * Quantities are in the component's stock unit (every consumable here has
 * conversion_factor 1, so stock unit == base unit). Fractions are the norm:
 * a Botox session uses 0.2 of a 100u vial, a peel 0.33 of a bottle, an
 * injection 0.01 of a 100-count syringe box.
 *
 * Services are matched by name so the seeder survives re-imports of the
 * catalogue with different ids. Re-runnable: rows are upserted on
 * (service_item_id, component_item_id).
 */
class ServiceBomSeeder extends Seeder
{
    /**
     * Consumables every clinical service consumes, whatever it is.
     * [component key, qty, is_optional]
     */
    private const UNIVERSAL = [
        ['gloves_exam', 0.02, false],     // 2 gloves out of a box of 100
        ['alcohol_swabs', 0.01, false],   // 2 swabs out of a box of 200
        ['wipes', 0.005, false],          // 1 wipe out of a box of 200
        ['mask', 0.02, false],            // 1 mask out of a box of 50
        ['biowaste_bag', 0.004, false],   // 1 bag off a roll of 25
    ];

    /**
     * Consumable lookup keys → distinctive substrings of the catalogue name.
     * Every listed substring must appear (case-insensitive) for a match.
     */
    private const COMPONENTS = [
        'botox_premium' => ['Allergan BOTOX'],
        'botox_value' => ['Botulax'],
        'dysport' => ['Dysport'],
        'filler_lips' => ['Juvederm Ultra 2'],
        'filler_mid' => ['Juvederm Ultra 3'],
        'filler_cheeks' => ['Juvederm Voluma'],
        'filler_jaw' => ['Juvederm Volux'],
        'filler_entry' => ['AMALIAN'],
        'filler_budget' => ['Revolax'],
        'filler_restylane' => ['Restylane'],
        'filler_body' => ['OPERA 5'],
        'profhilo' => ['Profhilo'],
        'booster_revitrane' => ['Revitrane'],
        'pdrn' => ['PDRN'],
        'salmon_dna' => ['Newclufill'],
        'sculptra' => ['Sculptra'],
        'booster_saypha' => ['SAYPHA'],
        'prp_kit' => ['AUTOSOMA'],
        'exosome_skin' => ['Exosomes', 'skin'],
        'exosome_scalp' => ['Exosomes', 'scalp'],
        'stem_cell' => ['Stem-cell ampoule'],
        'threads_cog' => ['COG 6D'],
        'threads_mono' => ['Mono pack'],
        'hyaluronidase' => ['Hyaluronidase'],
        'fat_dissolver' => ['Aqualyx'],
        'meso_cocktail' => ['Mesotherapy cocktails'],
        'cosmelan' => ['Cosmelan'],
        'numbing_cream' => ['J-Cain'],
        'emla' => ['EMLA'],
        'peel_glycolic' => ['Glycolic/Salicylic'],
        'peel_tca' => ['TCA peel solution'],
        'peel_jessner' => ['Jessner solution'],
        'gloves_exam' => ['Exam gloves'],
        'gloves_sterile' => ['Sterile surgical gloves'],
        'gloves_black' => ['Nitrile gloves black'],
        'mask' => ['Face masks'],
        'biowaste_bag' => ['Bio-waste bags'],
        'goggles_patient' => ['Patient laser goggles'],
        'gown' => ['patient gowns'],
        'drapes' => ['Disposable drapes'],
        'alcohol_swabs' => ['Alcohol swabs'],
        'gauze' => ['Sterile gauze'],
        'cotton' => ['Cotton balls'],
        'sharps_bin' => ['Sharps containers'],
        'drape_sheet' => ['Sterile drape sheets'],
        'syringe_1ml' => ['Syringes 1ml'],
        'syringe_2ml' => ['Syringes 2ml'],
        'syringe_5ml' => ['Syringes 5ml'],
        'needle_27g' => ['Needles 27G'],
        'needle_30g' => ['Needles 30G'],
        'cannula_22g' => ['Microcannulas 22G'],
        'cannula_25g' => ['Microcannulas 25G'],
        'mn_cartridge' => ['Microneedling cartridges'],
        'prp_tubes' => ['PRP collection tubes'],
        'saline' => ['Saline 0.9%'],
        'laser_gel' => ['Laser cooling gel'],
        'hair_ties' => ['LHR disposable hair ties'],
        'hydrabeauty' => ['HydraBeauty'],
        'autoclave_pouch' => ['Autoclave pouches'],
        'chlorhexidine' => ['Chlorhexidine'],
        'wipes' => ['disinfectant wipes'],
    ];

    /**
     * Service rules. Every rule whose substrings all appear in the service name
     * contributes its components, so a service can pick up more than one rule
     * (e.g. an acne + peel combo gets both the peel and the injectable lines).
     *
     * ['match' => [substrings], 'use' => [[component key, qty, optional?]]]
     */
    private function rules(): array
    {
        return [
            // ─── Botulinum toxin ───────────────────────────────────────────
            ['match' => ['Botox', 'facial wrinkles'], 'use' => [
                ['botox_premium', 0.2], ['saline', 0.02], ['syringe_1ml', 0.01],
                ['needle_27g', 0.01], ['numbing_cream', 0.01], ['gauze', 0.01],
                ['botox_value', 0.2, true], ['dysport', 0.2, true],
            ]],
            ['match' => ['Botox', 'hyperhidrosis'], 'use' => [
                ['botox_premium', 1.0], ['saline', 0.05], ['syringe_1ml', 0.02],
                ['needle_27g', 0.02], ['numbing_cream', 0.02], ['gauze', 0.02],
                ['dysport', 1.0, true],
            ]],
            ['match' => ['Nefertiti'], 'use' => [
                ['botox_premium', 0.25], ['saline', 0.02], ['syringe_1ml', 0.01],
                ['needle_27g', 0.01], ['numbing_cream', 0.01],
            ]],
            ['match' => ['Fine lines around eyes'], 'use' => [
                ['botox_premium', 0.12], ['saline', 0.02], ['needle_27g', 0.01],
                ['syringe_1ml', 0.01], ['numbing_cream', 0.01],
            ]],
            ['match' => ['Acne + wrinkle combo'], 'use' => [
                ['botox_premium', 0.1], ['needle_27g', 0.01], ['syringe_1ml', 0.01],
            ]],

            // ─── Hyaluronic-acid fillers ───────────────────────────────────
            ['match' => ['Filler', 'lips'], 'use' => [
                ['filler_lips', 1], ['needle_30g', 0.01], ['cannula_25g', 0.05],
                ['numbing_cream', 0.02], ['gauze', 0.01], ['hyaluronidase', 0.1, true],
            ]],
            ['match' => ['Filler', 'cheeks'], 'use' => [
                ['filler_cheeks', 1], ['cannula_22g', 0.05], ['needle_30g', 0.01],
                ['numbing_cream', 0.02], ['gauze', 0.01], ['hyaluronidase', 0.1, true],
            ]],
            ['match' => ['Filler', 'jawline'], 'use' => [
                ['filler_jaw', 1], ['cannula_22g', 0.05], ['needle_30g', 0.01],
                ['numbing_cream', 0.02], ['gauze', 0.01], ['hyaluronidase', 0.1, true],
            ]],
            ['match' => ['Filler', 'chin'], 'use' => [
                ['filler_mid', 0.5], ['needle_30g', 0.01], ['numbing_cream', 0.02],
                ['gauze', 0.01], ['hyaluronidase', 0.1, true],
            ]],
            ['match' => ['Filler', 'nasolabial'], 'use' => [
                ['filler_entry', 1], ['needle_30g', 0.01], ['numbing_cream', 0.02],
                ['gauze', 0.01], ['hyaluronidase', 0.1, true], ['filler_restylane', 1, true],
            ]],
            ['match' => ['Filler', 'tear trough'], 'use' => [
                ['filler_entry', 1], ['cannula_25g', 0.05], ['numbing_cream', 0.02],
                ['gauze', 0.01], ['hyaluronidase', 0.1, true], ['filler_restylane', 1, true],
            ]],
            ['match' => ['Body filler', 'buttocks'], 'use' => [
                ['filler_body', 1], ['cannula_22g', 0.1], ['numbing_cream', 0.05],
                ['gown', 1], ['drape_sheet', 1], ['gauze', 0.03],
            ]],
            ['match' => ['Jawline + double-chin'], 'use' => [
                ['fat_dissolver', 1], ['syringe_5ml', 0.02], ['needle_30g', 0.01],
                ['numbing_cream', 0.02], ['gauze', 0.01],
            ]],

            // ─── Skin boosters / bio-remodelling ───────────────────────────
            ['match' => ['Skin booster', 'Profhilo'], 'use' => [
                ['profhilo', 1], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['Hydro-lifting'], 'use' => [
                ['profhilo', 1], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['Skin Booster', 'general'], 'use' => [
                ['booster_revitrane', 0.34], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['Skin Booster', 'rejuvenation'], 'use' => [
                ['booster_revitrane', 0.34], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['Skin Booster', 'tightening'], 'use' => [
                ['booster_revitrane', 0.34], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['Skin booster', 'Sunekos'], 'use' => [
                ['salmon_dna', 1], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['Instant-lift injection'], 'use' => [
                ['booster_saypha', 1], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['Collagen stimulator'], 'use' => [
                ['sculptra', 1], ['saline', 0.2], ['syringe_5ml', 0.01],
                ['cannula_25g', 0.05], ['numbing_cream', 0.02],
            ]],

            // ─── Plasma / regenerative ─────────────────────────────────────
            ['match' => ['PRP face'], 'use' => [
                ['prp_kit', 0.1], ['prp_tubes', 0.1], ['syringe_5ml', 0.02],
                ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['PRP hair'], 'use' => [
                ['prp_kit', 0.1], ['prp_tubes', 0.1], ['syringe_5ml', 0.02],
                ['needle_30g', 0.01], ['emla', 0.05],
            ]],
            ['match' => ['Golden plasma'], 'use' => [
                ['prp_kit', 0.1], ['prp_tubes', 0.1], ['pdrn', 0.25],
                ['syringe_2ml', 0.02], ['needle_30g', 0.01],
            ]],
            ['match' => ['Therapeutic plasma'], 'use' => [
                ['prp_kit', 0.1], ['prp_tubes', 0.1], ['syringe_5ml', 0.02], ['needle_30g', 0.01],
            ]],
            ['match' => ['Stem Cell-Rich Plasma'], 'use' => [
                ['prp_kit', 0.15], ['prp_tubes', 0.1], ['stem_cell', 0.5],
                ['syringe_5ml', 0.02], ['needle_30g', 0.01],
            ]],
            ['match' => ['Stem cell therapy'], 'use' => [
                ['stem_cell', 1], ['syringe_5ml', 0.02], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['Exosome therapy'], 'use' => [
                ['exosome_skin', 1], ['exosome_scalp', 1, true], ['saline', 0.05],
                ['syringe_1ml', 0.02], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],

            // ─── Mesotherapy family ────────────────────────────────────────
            ['match' => ['Mesotherapy'], 'use' => [
                ['meso_cocktail', 1], ['syringe_1ml', 0.02], ['syringe_2ml', 0.02],
                ['needle_30g', 0.01], ['numbing_cream', 0.01], ['gauze', 0.01],
            ]],
            ['match' => ['combined bikini'], 'use' => [['meso_cocktail', 2]]],   // whitening + firming vials
            ['match' => ['Dark circles'], 'use' => [
                ['meso_cocktail', 1], ['needle_30g', 0.01], ['syringe_1ml', 0.02],
            ]],

            // ─── Microneedling / Dermapen ──────────────────────────────────
            ['match' => ['Dermapen'], 'use' => [
                ['mn_cartridge', 0.1], ['numbing_cream', 0.02], ['gauze', 0.02],
                ['meso_cocktail', 0.5, true],
            ]],
            ['match' => ['Microneedling'], 'use' => [
                ['mn_cartridge', 0.1], ['numbing_cream', 0.03], ['gauze', 0.02],
            ]],

            // ─── Chemical peels ────────────────────────────────────────────
            ['match' => ['Glycolic / Salicylic peel'], 'use' => [
                ['peel_glycolic', 0.33], ['cotton', 0.02], ['gauze', 0.02], ['saline', 0.1],
            ]],
            ['match' => ['Jessner peel'], 'use' => [
                ['peel_jessner', 0.33], ['cotton', 0.02], ['gauze', 0.02], ['saline', 0.1],
            ]],
            ['match' => ['TCA peel'], 'use' => [
                ['peel_tca', 0.33], ['cotton', 0.02], ['gauze', 0.02], ['saline', 0.1],
            ]],
            ['match' => ['peel'], 'use' => [
                ['peel_glycolic', 0.25], ['cotton', 0.02], ['gauze', 0.02],
            ]],
            ['match' => ['Active acne'], 'use' => [
                ['peel_glycolic', 0.33], ['cotton', 0.02], ['gauze', 0.02],
            ]],
            ['match' => ['COSMELAN'], 'use' => [
                ['cosmelan', 1], ['cotton', 0.03], ['gauze', 0.02], ['gloves_sterile', 0.05],
            ]],

            // ─── Threads ───────────────────────────────────────────────────
            ['match' => ['COG thread lift'], 'use' => [
                ['threads_cog', 2], ['numbing_cream', 0.05], ['gloves_sterile', 0.1],
                ['drape_sheet', 1], ['chlorhexidine', 0.02], ['gauze', 0.03], ['sharps_bin', 0.01],
            ]],
            ['match' => ['PDO thread lift'], 'use' => [
                ['threads_mono', 1], ['numbing_cream', 0.05], ['gloves_sterile', 0.1],
                ['chlorhexidine', 0.02], ['gauze', 0.03],
            ]],
            ['match' => ['Thread lift', 'course'], 'use' => [
                ['threads_mono', 0.5], ['numbing_cream', 0.05],
                ['gloves_sterile', 0.1], ['gauze', 0.03],
            ]],

            // ─── Fat dissolving / contouring ───────────────────────────────
            ['match' => ['Fat-dissolving injection'], 'use' => [
                ['fat_dissolver', 1], ['syringe_5ml', 0.02], ['needle_30g', 0.01], ['numbing_cream', 0.02],
            ]],
            ['match' => ['Fat-dissolving mesotherapy'], 'use' => [['fat_dissolver', 0.5]]],
            ['match' => ['Non-surgical contouring'], 'use' => [
                ['laser_gel', 0.1], ['gown', 1], ['cotton', 0.02],
            ]],
            ['match' => ['Body contouring'], 'use' => [
                ['laser_gel', 0.1], ['gown', 1], ['cotton', 0.02],
            ]],
            ['match' => ['Cellulite session'], 'use' => [
                ['laser_gel', 0.08], ['gown', 1], ['cotton', 0.02],
            ]],

            // ─── Laser / energy devices ────────────────────────────────────
            ['match' => ['laser'], 'use' => [
                ['laser_gel', 0.05], ['cotton', 0.02], ['gauze', 0.01], ['goggles_patient', 0.02], ['emla', 0.05, true],
            ]],
            ['match' => ['Hollywood Spectra'], 'use' => [
                ['laser_gel', 0.05], ['cotton', 0.02],
            ]],
            ['match' => ['HIFU'], 'use' => [
                ['laser_gel', 0.1], ['cotton', 0.02], ['emla', 0.05, true],
            ]],
            ['match' => ['Genius RF'], 'use' => [
                ['mn_cartridge', 0.1], ['numbing_cream', 0.03], ['laser_gel', 0.05],
            ]],
            ['match' => ['Stretch marks'], 'use' => [
                ['laser_gel', 0.08], ['mn_cartridge', 0.1], ['numbing_cream', 0.03],
            ]],
            ['match' => ['Enlarged pores'], 'use' => [
                ['hydrabeauty', 0.1], ['cotton', 0.02], ['laser_gel', 0.03],
            ]],
            ['match' => ['Intimate area tightening'], 'use' => [
                ['laser_gel', 0.05], ['gown', 1], ['drape_sheet', 1], ['cotton', 0.02],
            ]],

            // ─── Laser hair removal ────────────────────────────────────────
            ['match' => ['LHR', 'Full body'], 'use' => [
                ['laser_gel', 0.25], ['hair_ties', 0.02], ['gown', 1], ['cotton', 0.03], ['goggles_patient', 0.02],
            ]],
            ['match' => ['LHR', 'Legs'], 'use' => [
                ['laser_gel', 0.15], ['hair_ties', 0.02], ['cotton', 0.02],
            ]],
            ['match' => ['LHR', 'Back'], 'use' => [
                ['laser_gel', 0.15], ['hair_ties', 0.02], ['gown', 1], ['cotton', 0.02],
            ]],
            ['match' => ['LHR', 'Arms'], 'use' => [
                ['laser_gel', 0.1], ['hair_ties', 0.02], ['cotton', 0.02],
            ]],
            ['match' => ['LHR', 'Brazilian'], 'use' => [
                ['laser_gel', 0.08], ['hair_ties', 0.02], ['drape_sheet', 1], ['cotton', 0.02],
            ]],
            ['match' => ['LHR', 'Bikini'], 'use' => [
                ['laser_gel', 0.06], ['hair_ties', 0.02], ['drape_sheet', 1], ['cotton', 0.02],
            ]],
            ['match' => ['LHR', 'Underarms'], 'use' => [
                ['laser_gel', 0.04], ['hair_ties', 0.02], ['cotton', 0.02],
            ]],
            ['match' => ['LHR', 'Face'], 'use' => [
                ['laser_gel', 0.04], ['hair_ties', 0.02], ['cotton', 0.02], ['emla', 0.05, true],
            ]],

            // ─── Minor procedures / scars / wounds ─────────────────────────
            ['match' => ['Subcision'], 'use' => [
                ['needle_30g', 0.02], ['syringe_1ml', 0.02], ['numbing_cream', 0.03],
                ['gloves_sterile', 0.05], ['gauze', 0.03], ['chlorhexidine', 0.02],
                ['sharps_bin', 0.01], ['autoclave_pouch', 0.01],
            ]],
            ['match' => ['Pitted scars'], 'use' => [
                ['filler_budget', 1], ['needle_30g', 0.02], ['numbing_cream', 0.03],
                ['gloves_sterile', 0.05], ['gauze', 0.03], ['autoclave_pouch', 0.01],
            ]],
            ['match' => ['Scar repair'], 'use' => [
                ['pdrn', 0.5], ['needle_30g', 0.01], ['syringe_1ml', 0.02], ['numbing_cream', 0.02],
            ]],
            ['match' => ['wart removal'], 'use' => [
                ['gloves_sterile', 0.05], ['drape_sheet', 1], ['chlorhexidine', 0.02],
                ['gauze', 0.03], ['sharps_bin', 0.01], ['autoclave_pouch', 0.01],
            ]],
            ['match' => ['Genital wart'], 'use' => [
                ['gown', 1], ['drapes', 0.02],
            ]],
            ['match' => ['Wound / burn'], 'use' => [
                ['gauze', 0.05], ['chlorhexidine', 0.02], ['gloves_sterile', 0.05],
                ['drape_sheet', 1], ['saline', 0.25],
            ]],

            // ─── Consultation-only ─────────────────────────────────────────
            ['match' => ['Hair loss diagnosis'], 'use' => []],
        ];
    }

    public function run(): void
    {
        $services = ClinicItem::query()->withoutGlobalScopes()->where('type', 'service')->get();
        $consumables = ClinicItem::query()->withoutGlobalScopes()->where('type', 'consumable')->get();

        if ($services->isEmpty() || $consumables->isEmpty()) {
            $this->command?->warn('No services or consumables in the catalogue — nothing to link.');

            return;
        }

        $ids = $this->resolveComponents($consumables);
        $rules = $this->rules();

        $linked = 0;
        $skipped = [];

        foreach ($services as $service) {
            $name = $this->englishName($service->name);

            $lines = [];
            $matchedRule = false;
            foreach ($rules as $rule) {
                if (! $this->matches($name, $rule['match'])) {
                    continue;
                }
                $matchedRule = true;
                foreach ($rule['use'] as $use) {
                    [$key, $qty] = $use;
                    $optional = (bool) ($use[2] ?? false);
                    if (! isset($ids[$key])) {
                        continue;
                    }
                    $cid = $ids[$key];
                    // A component picked up by two rules keeps the larger qty,
                    // and stays auto-deducted if any rule says so.
                    $lines[$cid] = [
                        'qty' => max($qty, $lines[$cid]['qty'] ?? 0),
                        'optional' => ($lines[$cid]['optional'] ?? true) && $optional,
                    ];
                }
            }

            if (! $matchedRule) {
                $skipped[] = $name;
            }

            foreach (self::UNIVERSAL as [$key, $qty, $optional]) {
                if (! isset($ids[$key])) {
                    continue;
                }
                $cid = $ids[$key];
                $lines[$cid] = [
                    'qty' => max($qty, $lines[$cid]['qty'] ?? 0),
                    'optional' => ($lines[$cid]['optional'] ?? true) && $optional,
                ];
            }

            foreach ($lines as $componentId => $line) {
                ClinicItemComponent::updateOrCreate(
                    ['service_item_id' => $service->id, 'component_item_id' => $componentId],
                    ['qty_base' => $line['qty'], 'is_optional' => $line['optional']],
                );
                $linked++;
            }
        }

        $this->command?->info("Linked {$linked} BOM lines across {$services->count()} services.");

        $this->normaliseServiceCosts($services);
        foreach ($skipped as $name) {
            $this->command?->warn("  no rule matched (universal supplies only): {$name}");
        }
    }

    /**
     * The catalogue was imported with every service's default_cost copied from
     * its price. A visit line costs a service at default_cost + its BOM material
     * cost (VisitConsoleController::addItem), so leaving the copy in place bills
     * the materials twice and reports every treatment at a loss. Now that the
     * BOM carries the real material cost, the service's own cost is zero —
     * touched only where it still mirrors the price, so hand-set costs survive.
     */
    private function normaliseServiceCosts($services): void
    {
        $fixed = 0;
        foreach ($services as $service) {
            if ((float) $service->default_cost > 0
                && (float) $service->default_cost === (float) $service->default_price) {
                $service->default_cost = 0;
                $service->save();
                $fixed++;
            }
        }

        if ($fixed) {
            $this->command?->info("Zeroed default_cost on {$fixed} services (materials now come from the BOM).");
        }
    }

    /** Map each component key to a catalogue item id. */
    private function resolveComponents($consumables): array
    {
        $ids = [];
        foreach (self::COMPONENTS as $key => $needles) {
            $hit = $consumables->first(fn (ClinicItem $i) => $this->matches($this->englishName($i->name), $needles));
            if ($hit) {
                $ids[$key] = $hit->id;
            } else {
                $this->command?->warn("  consumable not found for '{$key}' (".implode(' + ', $needles).')');
            }
        }

        return $ids;
    }

    /** All needles must appear in the haystack, case-insensitively. */
    private function matches(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (stripos($haystack, $needle) === false) {
                return false;
            }
        }

        return $needles !== [];
    }

    /** Translatable names come back as an array; fall back to whatever is there. */
    private function englishName($name): string
    {
        if (is_array($name)) {
            return (string) ($name['en'] ?? reset($name) ?? '');
        }

        return (string) $name;
    }
}
