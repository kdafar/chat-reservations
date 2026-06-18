<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Partner;
use App\Models\Service;
use App\Services\Accounting\ChartOfAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Clinics (Partners) — v2 replacement for the Filament PartnerResource.
 *
 * The top-level tenant: a clinic owns branches, staff, and specialties, and
 * carries the print/legal details that appear on prescriptions & invoices.
 * Admin-only. `name` is translatable (en/ar).
 */
class PartnersController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        $u = $request->user();
        if (! $u || ! $u->can('view_any_partner')) {
            abort(403, 'Only admins can manage clinics.');
        }
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $status = $request->input('status', 'all');
        $locale = app()->getLocale();
        $query = Partner::query()->withCount('branches');
        if ($q !== '') { $query->where(fn ($w) => $w->where("name->{$locale}", 'like', "%{$q}%")->orWhere('name->en', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%")->orWhere('license_number', 'like', "%{$q}%")); }
        if ($status === 'active') { $query->where('is_active', true); } elseif ($status === 'inactive') { $query->where('is_active', false); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('id'),
                ['ID', 'Name (EN)', 'Slug', 'License #', 'Branches', 'Website', 'Email', 'Active'],
                fn ($p) => [$p->id, $n['en'] ?? reset($n), $p->slug, $p->license_number, $p->branches_count, $p->website, $p->email, $p->is_active ? 'Yes' : 'No'],
                'Partners',
                app()->getLocale() === 'ar',
            ),
            'partners-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $locale = app()->getLocale();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'status' => $request->input('status', 'all'),
        ];

        $query = Partner::query()->withCount('branches')->with(['services:id,name', 'account:id,code,name']);

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(function ($qq) use ($q, $locale) {
                $qq->where("name->{$locale}", 'like', "%{$q}%")
                    ->orWhere('name->en', 'like', "%{$q}%")
                    ->orWhere('slug', 'like', "%{$q}%")
                    ->orWhere('license_number', 'like', "%{$q}%");
            });
        }
        if ($filters['status'] === 'active') {
            $query->where('is_active', true);
        } elseif ($filters['status'] === 'inactive') {
            $query->where('is_active', false);
        }

        $page = $query->orderByDesc('id')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (Partner $p) => $this->present($p, $locale));

        return Inertia::render('Partners/Index', [
            'filters' => $filters,
            'page' => $page,
            'counts' => [
                'total' => Partner::query()->count(),
                'active' => Partner::query()->where('is_active', true)->count(),
                'inactive' => Partner::query()->where('is_active', false)->count(),
            ],
        ]);
    }

    /** Dedicated create page (replaces the old popup modal). */
    public function create(Request $request): Response
    {
        $this->authorizeAccess($request);

        return Inertia::render('Partners/Form', array_merge(
            ['mode' => 'create', 'partner' => null],
            $this->formSupport($request),
        ));
    }

    /** Dedicated edit page (replaces the old popup modal). */
    public function edit(Request $request, Partner $partner): Response
    {
        $this->authorizeAccess($request);
        $partner->load('account:id,code,name', 'services:id');

        return Inertia::render('Partners/Form', array_merge(
            ['mode' => 'edit', 'partner' => $this->present($partner, app()->getLocale())],
            $this->formSupport($request),
        ));
    }

    /** Shared select/options + permission flags for the create & edit pages. */
    protected function formSupport(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'services' => Service::query()->orderBy("name->{$locale}")->get(['id', 'name'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => (string) $s->getTranslation('name', $locale)])->all(),
            'accounts' => Account::postableOptions([Account::TYPE_REVENUE]),
            'can_edit_accounting' => (bool) $request->user()?->can('update_accounting_accounts'),
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateData($request, null);

        $partner = new Partner();
        $this->fillFromData($partner, $data);
        $changed = $this->applyAccountLink($partner, $request, $data);
        $partner->save();
        $partner->services()->sync($data['services'] ?? []);
        if ($changed) {
            app(ChartOfAccounts::class)->refresh();
        }

        return redirect()->route('v2.partners.index')
            ->with('flash', ['type' => 'success', 'message' => 'Clinic created.']);
    }

    public function update(Request $request, Partner $partner): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateData($request, $partner);

        $this->fillFromData($partner, $data);
        $changed = $this->applyAccountLink($partner, $request, $data);
        $partner->save();
        $partner->services()->sync($data['services'] ?? []);
        if ($changed) {
            app(ChartOfAccounts::class)->refresh();
        }

        return redirect()->route('v2.partners.index')
            ->with('flash', ['type' => 'success', 'message' => 'Clinic updated.']);
    }

    /**
     * Set the partner's default-revenue account link — only an accountant
     * (update_accounting_accounts) may change it; other admins leave it as-is.
     * Returns true when the link actually changed (so the caller can drop the
     * ChartOfAccounts cache after saving).
     */
    protected function applyAccountLink(Partner $partner, Request $request, array $data): bool
    {
        if (! $request->user()?->can('update_accounting_accounts')) {
            return false;
        }
        $partner->account_id = $data['account_id'] ?: null;

        return $partner->isDirty('account_id');
    }

    public function destroy(Request $request, Partner $partner): RedirectResponse
    {
        $this->authorizeAccess($request);

        if ($partner->branches()->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'This clinic still has branches. Reassign or remove them first.']);
        }
        $partner->update(['is_active' => false]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Clinic deactivated.']);
    }

    protected function validateData(Request $request, ?Partner $partner): array
    {
        return $request->validate([
            'name_en' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('partners', 'slug')->ignore($partner?->id)],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'license_number' => ['nullable', 'string', 'max:255'],
            'footer_text' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],
            'services' => ['array'],
            'services.*' => ['integer', 'exists:services,id'],
        ]);
    }

    protected function fillFromData(Partner $partner, array $data): void
    {
        $partner->setTranslation('name', 'en', $data['name_en']);
        $partner->setTranslation('name', 'ar', $data['name_ar'] ?: $data['name_en']);
        $partner->slug = Str::slug($data['slug']);
        $partner->website = $data['website'] ?? null;
        $partner->email = $data['email'] ?? null;
        $partner->license_number = $data['license_number'] ?? null;
        $partner->footer_text = $data['footer_text'] ?? null;
        $partner->is_active = (bool) ($data['is_active'] ?? false);
    }

    protected function present(Partner $p, string $locale): array
    {
        return [
            'id' => $p->id,
            'name' => (string) $p->getTranslation('name', $locale),
            'name_en' => (string) $p->getTranslation('name', 'en'),
            'name_ar' => (string) $p->getTranslation('name', 'ar'),
            'slug' => $p->slug,
            'website' => $p->website,
            'email' => $p->email,
            'license_number' => $p->license_number,
            'footer_text' => $p->footer_text,
            'is_active' => (bool) $p->is_active,
            'account_id' => $p->account_id,
            'account_label' => $p->account ? $p->account->code.' — '.$p->account->name : null,
            'branches_count' => $p->branches_count,
            'service_ids' => $p->services->pluck('id')->all(),
            'specialties' => $p->services->map(fn ($s) => (string) $s->getTranslation('name', $locale))->all(),
        ];
    }
}
