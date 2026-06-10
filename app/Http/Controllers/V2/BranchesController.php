<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\City;
use App\Models\Partner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Branches — v2 replacement for the (clinic-relevant slice of) Filament
 * BranchResource. The Filament version carries a lot of restaurant-era cruft
 * (cuisines, delivery/pickup, media crops). This screen keeps only what a
 * clinic actually configures: identity, contact, location, booking horizon.
 *
 * Admin-only. `name` is translatable (en/ar) via Spatie HasTranslations.
 */
class BranchesController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        $u = $request->user();
        if (! $u || ! $u->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Only admins can manage branches.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $locale = app()->getLocale();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', 'all'),
        ];

        $query = Branch::query()->with(['partner:id,name', 'city:id,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q, $locale) {
                $qq->where("name->{$locale}", 'like', "%{$q}%")
                    ->orWhere('name->en', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('license_number', 'like', "%{$q}%");
            });
        }
        if ($filters['status'] === 'available') {
            $query->where('is_available', true);
        } elseif ($filters['status'] === 'unavailable') {
            $query->where('is_available', false);
        }

        $page = $query->orderBy('id', 'desc')->paginate(25)->withQueryString();

        $page->getCollection()->transform(fn (Branch $b) => $this->present($b, $locale));

        return Inertia::render('Branches/Index', [
            'filters' => $filters,
            'page' => $page,
            'partners' => Partner::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => (string) $p->getTranslation('name', $locale)])->all(),
            'cities' => City::query()->orderBy("name->{$locale}")->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => (string) $c->getTranslation('name', $locale)])->all(),
            'counts' => [
                'total' => Branch::query()->count(),
                'available' => Branch::query()->where('is_available', true)->count(),
                'unavailable' => Branch::query()->where('is_available', false)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateData($request, null);

        $branch = new Branch();
        $this->fillFromData($branch, $data);
        $branch->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Branch created.']);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateData($request, $branch);

        $this->fillFromData($branch, $data);
        $branch->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Branch updated.']);
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeAccess($request);

        // Soft guard: never hard-delete a branch with history — just hide it.
        $branch->update(['is_available' => false]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Branch marked unavailable.']);
    }

    protected function validateData(Request $request, ?Branch $branch): array
    {
        return $request->validate([
            'partner_id' => ['required', 'integer', 'exists:partners,id'],
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('branches', 'slug')->ignore($branch?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:191'],
            'license_number' => ['nullable', 'string', 'max:191'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'max_booking_days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_available' => ['boolean'],
            'is_hub' => ['boolean'],
        ]);
    }

    protected function fillFromData(Branch $branch, array $data): void
    {
        $branch->setTranslation('name', 'en', $data['name_en']);
        $branch->setTranslation('name', 'ar', $data['name_ar'] ?: $data['name_en']);

        $branch->partner_id = $data['partner_id'];
        $branch->phone = $data['phone'] ?? null;
        $branch->email = $data['email'] ?? null;
        $branch->license_number = $data['license_number'] ?? null;
        $branch->address = $data['address'] ?? null;
        $branch->city_id = $data['city_id'] ?? null;
        $branch->max_booking_days = $data['max_booking_days'];
        $branch->is_available = (bool) ($data['is_available'] ?? false);
        $branch->is_hub = (bool) ($data['is_hub'] ?? false);

        // Respect an explicit slug; otherwise let the model's creating() hook
        // derive one on first save.
        if (! empty($data['slug'])) {
            $branch->slug = Str::slug($data['slug']);
        }
    }

    protected function present(Branch $b, string $locale): array
    {
        return [
            'id' => $b->id,
            'name' => (string) $b->getTranslation('name', $locale),
            'name_en' => (string) $b->getTranslation('name', 'en'),
            'name_ar' => (string) $b->getTranslation('name', 'ar'),
            'slug' => $b->slug,
            'partner_id' => $b->partner_id,
            'partner_name' => $b->partner ? (string) $b->partner->getTranslation('name', $locale) : null,
            'phone' => $b->phone,
            'email' => $b->email,
            'license_number' => $b->license_number,
            'address' => $b->address,
            'city_id' => $b->city_id,
            'city_name' => $b->city ? (string) $b->city->getTranslation('name', $locale) : null,
            'max_booking_days' => $b->max_booking_days,
            'is_available' => (bool) $b->is_available,
            'is_hub' => (bool) $b->is_hub,
        ];
    }
}
