<?php

namespace App\Services\MenuSync;

use App\Models\Branch;
use App\Models\ExternalRef;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class Importer
{
    public function __construct(
        protected string $source, // provider key
    ) {}

    public function run(Branch $branch, array $enData, array $arData = []): void
    {
        $arById = collect($arData)->keyBy('id');

        $activeMenus = collect();
        $activeSections = collect();
        $activeItems = collect();
        $activeGroups = collect();
        $activeOptions = collect();

        DB::transaction(function () use ($branch, $enData, $arById, &$activeMenus, &$activeSections, &$activeItems, &$activeGroups, &$activeOptions) {

            foreach ($enData as $catEn) {
                $catAr = $arById->get($catEn['id']) ?? [];

                // MENU (category-as-menu)
                $menu = $this->upsertMenu(
                    branchId: $branch->id,
                    externalId: $catEn['id'] ?? null,
                    nameEn: $catEn['name'] ?? 'Category',
                    nameAr: $catAr['name'] ?? ($catEn['name'] ?? 'Category'),
                    descEn: $catEn['description'] ?? null,
                    descAr: $catAr['description'] ?? ($catEn['description'] ?? null)
                );
                $activeMenus->push($menu->id);

                // SECTION (synthetic section under the same menu)
                $section = $this->upsertSection(
                    menuId: $menu->id,
                    externalId: ($catEn['id'] ?? Str::random(12)).'::root',
                    nameEn: $catEn['name'] ?? 'Category',
                    nameAr: $catAr['name'] ?? ($catEn['name'] ?? 'Category'),
                );
                $activeSections->push($section->id);

                // ITEMS
                $itemsAr = collect($catAr['items'] ?? [])->keyBy('id');
                foreach ((array) ($catEn['items'] ?? []) as $itEn) {
                    $itAr = $itemsAr->get($itEn['id']) ?? [];

                    $item = $this->upsertItem(
                        branchId: $branch->id,
                        sectionId: $section->id,
                        externalId: $itEn['id'] ?? null,
                        nameEn: $itEn['name'] ?? '[Item]',
                        nameAr: $itAr['name'] ?? ($itEn['name'] ?? '[Item]'),
                        descEn: $itEn['description'] ?? null,
                        descAr: $itAr['description'] ?? ($itEn['description'] ?? null),
                        price: (float) ($itEn['price'] ?? 0),
                        sku: (string) ($itEn['sku'] ?? ''),
                        isAvailable: true,
                        imageUrl: $itEn['image_url'] ?? null,
                    );
                    $activeItems->push($item->id);

                    // MODIFIER GROUPS
                    $groupsAr = collect($itAr['addon_groups'] ?? $itAr['groups'] ?? [])->keyBy('id');
                    foreach ((array) ($itEn['addon_groups'] ?? $itEn['groups'] ?? []) as $gEn) {
                        $gAr = $groupsAr->get($gEn['id']) ?? [];

                        $group = $this->upsertGroup(
                            branchId: $branch->id,
                            externalId: $gEn['id'] ?? null,
                            nameEn: $gEn['title'] ?? $gEn['name'] ?? 'Group',
                            nameAr: $gAr['title'] ?? $gAr['name'] ?? ($gEn['title'] ?? 'Group'),
                            isRequired: (bool) ($gEn['is_required'] ?? false),
                            min: (int) ($gEn['min'] ?? 0),
                            max: (int) ($gEn['max'] ?? 1),
                        );
                        $activeGroups->push($group->id);

                        // attach item<->group
                        $item->modifierGroups()->syncWithoutDetaching([$group->id]);

                        // OPTIONS
                        $optsAr = collect($gAr['options'] ?? [])->keyBy('id');
                        foreach ((array) ($gEn['options'] ?? []) as $oEn) {
                            $oAr = $optsAr->get($oEn['id']) ?? [];
                            $opt = $this->upsertOption(
                                groupId: $group->id,
                                externalId: $oEn['id'] ?? null,
                                nameEn: $oEn['title'] ?? $oEn['name'] ?? 'Option',
                                nameAr: $oAr['title'] ?? $oAr['name'] ?? ($oEn['title'] ?? 'Option'),
                                priceDelta: (float) ($oEn['price'] ?? $oEn['price_delta'] ?? 0),
                                isDefault: (bool) ($oEn['is_default'] ?? false),
                            );
                            $activeOptions->push($opt->id);
                        }
                    }
                }
            }

            // Toggle inactives (no hard deletes)
            Menu::where('branch_id', $branch->id)
                ->whereNotIn('id', $activeMenus)->update(['is_active' => false]);

            MenuSection::whereIn('menu_id', Menu::where('branch_id', $branch->id)->pluck('id'))
                ->whereNotIn('id', $activeSections)->update(['sort_order' => 9999]);

            MenuItem::where('branch_id', $branch->id)
                ->whereNotIn('id', $activeItems)->update(['is_available' => false]);

            // Groups/Options cleanup intentionally skipped (often shared)
        });
    }

    // ——— helpers ———

    protected function upsertMenu(
        int $branchId, ?string $externalId,
        string $nameEn, string $nameAr,
        ?string $descEn, ?string $descAr
    ): Menu {
        [$menu, $ref, $reason] = $this->resolveRefAs('menu', $externalId, Menu::class);

        // If ref points to other branch, treat as stale
        if ($menu && $menu->branch_id !== $branchId) {
            \Log::warning('Importer: menu ref points to different branch; resetting', [
                'source' => $this->source, 'external_id' => $externalId,
                'ref_branch' => $menu->branch_id, 'expected_branch' => $branchId,
            ]);
            $this->deleteRef($ref);
            $menu = null;
        }

        // If resolver said stale/wrong-type, drop the ref so we can rebind
        if (in_array($reason, ['stale-null', 'wrong-type'], true)) {
            $this->deleteRef($ref);
        }

        // Create/find a local menu if none
        if (! $menu) {
            // Use a "natural key" for idempotency within a branch
            $menu = Menu::firstOrNew([
                'branch_id' => $branchId,
                'name->en' => $nameEn,
            ]);

            // EXTRA HARDENING: firstOrNew always returns a model, but just in case…
            if (! $menu instanceof Menu) {
                \Log::error('Importer: firstOrNew(Menu) returned non-model/null; instantiating', [
                    'branch_id' => $branchId, 'name_en' => $nameEn,
                ]);
                $menu = new Menu;
            }
        }

        // Assign fields (safe now)
        $menu->branch_id = $branchId;
        $menu->name = ['en' => $nameEn, 'ar' => $nameAr];
        $menu->description = ['en' => $descEn, 'ar' => $descAr];
        $menu->is_active = true;
        $menu->save();

        // Rebind ref
        if ($externalId) {
            $this->storeRef('menu', $externalId, $menu);
        }

        // DEBUG breadcrumb
        \Log::debug('Importer: upsertMenu OK', [
            'menu_id' => $menu->id, 'branch_id' => $branchId, 'external_id' => $externalId, 'reason' => $reason,
        ]);

        return $menu;
    }

    protected function upsertSection(int $menuId, string $externalId, string $nameEn, string $nameAr): MenuSection
    {
        [$section, $ref, $reason] = $this->resolveRefAs('section', $externalId, MenuSection::class);

        if ($section && $section->menu_id !== $menuId) {
            Log::warning('ExternalRef(section) points to different menu; resetting', [
                'source' => $this->source, 'external_id' => $externalId,
                'ref_menu' => $section->menu_id, 'expected_menu' => $menuId,
            ]);
            $this->deleteRef($ref);
            $section = null;
        }

        if (! $section) {
            $section = MenuSection::firstOrNew([
                'menu_id' => $menuId,
                'name->en' => $nameEn,
            ]);
        }

        $section->menu_id = $menuId;
        $section->name = ['en' => $nameEn, 'ar' => $nameAr];
        $section->sort_order ??= 0;
        $section->save();

        $this->storeRef('section', $externalId, $section);

        return $section;
    }

    protected function upsertItem(
        int $branchId, int $sectionId, ?string $externalId,
        string $nameEn, string $nameAr, ?string $descEn, ?string $descAr,
        float $price, string $sku, bool $isAvailable, ?string $imageUrl
    ): MenuItem {
        [$item, $ref, $reason] = $this->resolveRefAs('item', $externalId, MenuItem::class);

        if ($item && ($item->branch_id !== $branchId || $item->menu_section_id !== $sectionId)) {
            Log::warning('ExternalRef(item) points to wrong branch/section; resetting', [
                'source' => $this->source, 'external_id' => $externalId,
                'ref_branch' => $item->branch_id, 'exp_branch' => $branchId,
                'ref_section' => $item->menu_section_id, 'exp_section' => $sectionId,
            ]);
            $this->deleteRef($ref);
            $item = null;
        }

        if (! $item) {
            // Build unique finder without null JSON key
            $criteria = [
                'menu_section_id' => $sectionId,
                'branch_id' => $branchId,
            ];
            if ($sku) {
                $criteria['sku'] = $sku;
            } else {
                $criteria['name->en'] = $nameEn;
            }
            $item = MenuItem::firstOrNew($criteria);
        }

        $item->menu_section_id = $sectionId;
        $item->branch_id = $branchId;
        $item->name = ['en' => $nameEn, 'ar' => $nameAr];
        $item->description = ['en' => $descEn, 'ar' => $descAr];
        $item->price = $price;
        $item->is_available = $isAvailable;
        $item->save();

        // Image handling via url+hash; schedule downloader if changed
        if ($imageUrl) {
            $canon = $this->canonicalizeUrl($imageUrl);
            $hash = $canon ? sha1($canon) : null;
            if ($hash && $item->image_src_hash !== $hash) {
                $item->image_src_url = $canon;
                $item->image_src_hash = $hash;
                $item->save();

                \App\Jobs\DownloadMenuItemImage::dispatch($item->id, $imageUrl, $hash)
                    ->onQueue(config('queue.image_queue', 'images'));
            }
        }

        if ($externalId) {
            $this->storeRef('item', $externalId, $item);
        }

        return $item;
    }

    protected function upsertGroup(
        int $branchId, ?string $externalId,
        string $nameEn, string $nameAr,
        bool $isRequired, int $min, int $max
    ): ModifierGroup {
        [$group, $ref] = $this->resolveRef('group', $externalId);

        if ($group && $group->branch_id !== $branchId) {
            Log::warning('ExternalRef(group) points to different branch; resetting', [
                'source' => $this->source, 'external_id' => $externalId,
                'ref_branch' => $group->branch_id, 'expected_branch' => $branchId,
            ]);
            $this->deleteRef($ref);
            $group = null;
        }

        if (! $group) {
            $group = ModifierGroup::firstOrNew([
                'branch_id' => $branchId,
                'name->en' => $nameEn,
            ]);
        }

        $group->branch_id = $branchId;
        $group->name = ['en' => $nameEn, 'ar' => $nameAr];
        $group->is_required = $isRequired;
        $group->min_choices = $min;
        $group->max_choices = $max;
        $group->save();

        if ($externalId) {
            $this->storeRef('group', $externalId, $group);
        }

        return $group;
    }

    protected function upsertOption(
        int $groupId, ?string $externalId,
        string $nameEn, string $nameAr,
        float $priceDelta, bool $isDefault
    ): ModifierOption {
        [$opt, $ref] = $this->resolveRef('option', $externalId);

        if ($opt && $opt->modifier_group_id !== $groupId) {
            Log::warning('ExternalRef(option) points to different group; resetting', [
                'source' => $this->source, 'external_id' => $externalId,
                'ref_group' => $opt->modifier_group_id, 'expected_group' => $groupId,
            ]);
            $this->deleteRef($ref);
            $opt = null;
        }

        if (! $opt) {
            $opt = ModifierOption::firstOrNew([
                'modifier_group_id' => $groupId,
                'name->en' => $nameEn,
            ]);
        }

        $opt->modifier_group_id = $groupId;
        $opt->name = ['en' => $nameEn, 'ar' => $nameAr];
        $opt->price_delta = $priceDelta;
        $opt->is_default = $isDefault;
        $opt->save();

        if ($externalId) {
            $this->storeRef('option', $externalId, $opt);
        }

        return $opt;
    }

    /**
     * Resolve an ExternalRef to its local model, guarding against nulls and soft-deletes.
     * Returns [localModel|null, ExternalRef|null]. If local is null, the ref is considered stale.
     */
    protected function resolveRef(string $entity, ?string $externalId): array
    {
        if (! $externalId) {
            return [null, null];
        }

        $ref = $this->findRef($entity, $externalId);
        if (! $ref) {
            return [null, null];
        }

        // If your ExternalRef::local() uses ->withTrashed(), this will also return trashed models.
        $local = $ref->local ?? null;

        if (! $local) {
            Log::warning('Stale ExternalRef detected', [
                'source' => $this->source, 'entity' => $entity, 'external_id' => $externalId,
            ]);

            return [null, $ref]; // caller may delete and recreate
        }

        return [$local, $ref];
    }

    protected function findRef(string $entity, string $externalId): ?ExternalRef
    {
        return ExternalRef::where([
            'source' => $this->source,
            'entity' => $entity,
            'external_id' => $externalId,
        ])->first();
    }

    protected function storeRef(string $entity, string $externalId, $model): void
    {
        ExternalRef::updateOrCreate(
            ['source' => $this->source, 'entity' => $entity, 'external_id' => $externalId],
            ['local_type' => get_class($model), 'local_id' => $model->getKey()]
        );
    }

    protected function downloadImage(string $url, ?string $current = null): ?string
    {
        try {
            $resp = Http::timeout(20)->retry(2, 200)->get($url);
            if (! $resp->ok()) {
                throw new \RuntimeException('HTTP '.$resp->status());
            }

            $binary = $resp->body();

            $img = Image::read($binary)->scaleDown(1024, 1024);

            $name = Str::random(40).'.webp';
            $path = 'menu_images/'.$name;

            Storage::disk('public')->put($path, (string) $img->toWebp(80));

            if ($current && Storage::disk('public')->exists($current) && $current !== $path) {
                Storage::disk('public')->delete($current);
            }

            return $path;
        } catch (\Throwable $e) {
            Log::error('Import image failed', ['url' => $url, 'e' => $e->getMessage()]);

            return $current;
        }
    }

    protected function canonicalizeUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        try {
            $p = parse_url($url);
        } catch (\Throwable) {
            return $url;
        }

        $scheme = isset($p['scheme']) ? strtolower($p['scheme']).'://' : '';
        $host = isset($p['host']) ? strtolower($p['host']) : '';
        $port = isset($p['port']) ? ':'.$p['port'] : '';
        $path = $p['path'] ?? '';
        $query = '';

        if (! empty($p['query'])) {
            parse_str($p['query'], $q);
            ksort($q);
            $query = $q ? '?'.http_build_query($q) : '';
        }

        return $scheme.$host.$port.$path.$query;
    }

    /**
     * Resolve an ExternalRef for a given entity and ensure it's an instance of $expectedClass.
     * Returns [Model|null, ExternalRef|null, string reason]
     *   reason: "ok" | "not-found" | "stale-null" | "wrong-type"
     */
    protected function resolveRefAs(string $entity, ?string $externalId, string $expectedClass): array
    {
        if (! $externalId) {
            return [null, null, 'not-found'];
        }

        $ref = $this->findRef($entity, $externalId);
        if (! $ref) {
            return [null, null, 'not-found'];
        }

        // NOTE: requires ExternalRef::local() ->withTrashed()
        $local = $ref->local ?? null;
        if (! $local) {
            \Log::warning('Importer: stale ExternalRef (null local)', [
                'source' => $this->source, 'entity' => $entity, 'external_id' => $externalId,
            ]);

            return [null, $ref, 'stale-null'];
        }

        if (! $local instanceof $expectedClass) {
            \Log::warning('Importer: wrong-type ExternalRef', [
                'source' => $this->source, 'entity' => $entity, 'external_id' => $externalId,
                'have' => get_class($local), 'want' => $expectedClass,
            ]);

            return [null, $ref, 'wrong-type'];
        }

        return [$local, $ref, 'ok'];
    }

    protected function deleteRef(?ExternalRef $ref): void
    {
        if ($ref) {
            try {
                $ref->delete();
            } catch (\Throwable $e) {
                \Log::warning('Importer: failed deleting ExternalRef', ['id' => $ref->id, 'e' => $e->getMessage()]);
            }
        }
    }
}
