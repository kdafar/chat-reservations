<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\ClinicCatalogCategory;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Catalogue categories ("Peels", "Lasers", "Skincare") that file clinic items
 * and packages. JSON endpoints driven by the manager dialog on the v2 Clinic
 * Items screen. Gated by the clinic-items permissions: viewing the catalogue
 * lets you list categories; editing it lets you change them.
 *
 * Deleting a category never deletes items/packages — the category_id FKs are
 * nullOnDelete, so they simply become uncategorised.
 */
class ClinicCatalogCategoriesController extends Controller
{
    use ResolvesAccessibleClinics;

    protected function authorizeView(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_clinic_items')) {
            abort(403, 'Not authorized to view catalogue categories.');
        }
    }

    protected function authorizeWrite(Request $request): void
    {
        $this->authorizeView($request);
        if (! $request->user()->can('update_clinic_items')) {
            abort(403, 'Not authorized to manage catalogue categories.');
        }
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeView($request);

        return response()->json(['categories' => $this->list()]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);
        $data = $this->validated($request);

        $category = ClinicCatalogCategory::create($data + [
            'partner_id' => $this->defaultPartnerId(), // clinic-owned, like clinic items
            'sort_order' => ((int) ClinicCatalogCategory::query()->max('sort_order')) + 1,
        ]);

        return response()->json(['category' => $this->present($category->loadCount(['items', 'packages'])), 'categories' => $this->list()], 201);
    }

    public function update(Request $request, ClinicCatalogCategory $category): JsonResponse
    {
        $this->authorizeWrite($request);
        $category->update($this->validated($request));

        return response()->json(['category' => $this->present($category->loadCount(['items', 'packages'])), 'categories' => $this->list()]);
    }

    public function destroy(Request $request, ClinicCatalogCategory $category): JsonResponse
    {
        $this->authorizeWrite($request);
        $category->delete(); // items/packages → uncategorised (FK nullOnDelete)

        return response()->json(['categories' => $this->list()]);
    }

    /** Persist a new order: `ids` in display order → sort_order 1..n. */
    public function reorder(Request $request): JsonResponse
    {
        $this->authorizeWrite($request);
        $ids = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'distinct'],
        ])['ids'];

        // Only categories visible to this user can be reordered.
        $visible = ClinicCatalogCategory::query()->whereIn('id', $ids)->pluck('id')->all();
        $visible = array_flip($visible);

        DB::transaction(function () use ($ids, $visible) {
            $n = 0;
            foreach ($ids as $id) {
                if (! isset($visible[(int) $id])) {
                    continue;
                }
                ClinicCatalogCategory::query()->whereKey($id)->update(['sort_order' => ++$n]);
            }
        });

        return response()->json(['categories' => $this->list()]);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:80'],
            'name.ar' => ['nullable', 'string', 'max:80'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $ar = trim((string) ($data['name']['ar'] ?? ''));

        return [
            'name' => ['en' => trim($data['name']['en']), 'ar' => $ar === '' ? null : $ar],
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    protected function list(): array
    {
        return ClinicCatalogCategory::query()
            ->withCount(['items', 'packages'])
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn (ClinicCatalogCategory $c) => $this->present($c))
            ->all();
    }

    protected function present(ClinicCatalogCategory $c): array
    {
        $n = (array) $c->name;

        return [
            'id' => $c->id,
            'name' => ['en' => $n['en'] ?? '', 'ar' => $n['ar'] ?? ''],
            'label' => $c->label(app()->getLocale()),
            'sort_order' => (int) $c->sort_order,
            'is_active' => (bool) $c->is_active,
            'items_count' => (int) ($c->items_count ?? 0),
            'packages_count' => (int) ($c->packages_count ?? 0),
        ];
    }
}
