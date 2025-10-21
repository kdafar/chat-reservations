<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Multitenancy\Jobs\NotTenantAware;

class ImportBranchMenus implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $partnerId, public string $path) {}

    public function handle(): void
    {
        Excel::import(new class($this->partnerId) implements \Maatwebsite\Excel\Concerns\ToCollection
        {
            public function __construct(private int $partnerId) {}

            public function collection(\Illuminate\Support\Collection $rows)
            {
                $header = collect($rows->shift() ?? [])->map(fn ($v) => strtolower(trim((string) $v)));

                $i = fn (string $key) => $header->search($key);

                foreach ($rows as $row) {
                    $branchId = (int) ($row[$i('branch_id')] ?? 0);
                    if (! $branchId) {
                        continue;
                    }

                    // safety: ensure branch belongs to active partner
                    $belongs = Branch::where('partner_id', $this->partnerId)->where('id', $branchId)->exists();
                    if (! $belongs) {
                        continue;
                    }

                    $menuEn = (string) ($row[$i('menu_en')] ?? '');
                    $menuAr = (string) ($row[$i('menu_ar')] ?? $menuEn);

                    $sectionEn = (string) ($row[$i('section_en')] ?? '');
                    $sectionAr = (string) ($row[$i('section_ar')] ?? $sectionEn);

                    $itemEn = (string) ($row[$i('item_en')] ?? '');
                    $itemAr = (string) ($row[$i('item_ar')] ?? $itemEn);

                    $descEn = (string) ($row[$i('description_en')] ?? '');
                    $descAr = (string) ($row[$i('description_ar')] ?? '');

                    $sku = trim((string) ($row[$i('sku')] ?? ''));
                    $price = (float) ($row[$i('price')] ?? 0);
                    $avail = (int) ($row[$i('is_available')] ?? 1);

                    if ($menuEn === '' || $sectionEn === '' || ($itemEn === '' && $itemAr === '')) {
                        continue;
                    }

                    // upsert menu (branch + name)
                    $menu = Menu::firstOrCreate(
                        ['branch_id' => $branchId, 'name->en' => $menuEn],
                        ['name' => ['en' => $menuEn, 'ar' => $menuAr], 'is_active' => 1]
                    );

                    // upsert section in that menu
                    $section = MenuSection::firstOrCreate(
                        ['menu_id' => $menu->id, 'name->en' => $sectionEn],
                        ['name' => ['en' => $sectionEn, 'ar' => $sectionAr], 'sort_order' => 0]
                    );

                    // upsert item (by SKU within section, else by name)
                    $item = MenuItem::firstOrNew([
                        'menu_section_id' => $section->id,
                        'branch_id' => $branchId,
                        'sku' => $sku ?: null,
                        'name->en' => $sku ? null : $itemEn,
                    ]);

                    $item->name = ['en' => $itemEn ?: $itemAr, 'ar' => $itemAr ?: $itemEn];
                    $item->description = ['en' => $descEn, 'ar' => $descAr];
                    $item->price = $price;
                    $item->is_available = (bool) $avail;
                    $item->save();
                }
            }
        }, $this->path);
    }
}
