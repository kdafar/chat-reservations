<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
use App\Models\Branch;
use App\Models\City;
use App\Models\Partner;
use App\Services\Accounting\ChartOfAccounts;
use App\Services\Clinic\WorkingHoursService;
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
        if (! $u || ! $u->can('view_any_branch')) {
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

        $query = Branch::query()->with(['partner:id,name', 'city:id,name', 'account:id,code,name']);

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
        $this->validateHours($request, $data);

        $branch = new Branch();
        $this->fillFromData($branch, $data);
        $changed = $this->applyAccountLink($branch, $request, $data);
        $branch->save();
        if ($changed) {
            app(ChartOfAccounts::class)->refresh();
        }
        $this->saveHours($branch, $data);

        return redirect()->route('v2.branches.index')
            ->with('flash', ['type' => 'success', 'message' => 'Branch created.']);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateData($request, $branch);
        $this->validateHours($request, $data);

        $this->fillFromData($branch, $data);
        $changed = $this->applyAccountLink($branch, $request, $data);
        $branch->save();
        if ($changed) {
            app(ChartOfAccounts::class)->refresh();
        }
        $notes = $this->saveHours($branch, $data);

        return redirect()->route('v2.branches.index')
            ->with('flash', [
                'type' => $notes ? 'warning' : 'success',
                'message' => trim('Branch updated. '.implode(' ', $notes)),
            ]);
    }

    /** Dedicated create page (replaces the old popup modal). */
    public function create(Request $request): Response
    {
        $this->authorizeAccess($request);

        return Inertia::render('Branches/Form', array_merge(
            ['mode' => 'create', 'branch' => null, 'hours' => app(WorkingHoursService::class)->branchHoursPayload(0)],
            $this->formSupport($request),
        ));
    }

    /** Dedicated edit page (replaces the old popup modal). */
    public function edit(Request $request, Branch $branch): Response
    {
        $this->authorizeAccess($request);
        $branch->load('partner:id,name', 'city:id,name', 'account:id,code,name');

        return Inertia::render('Branches/Form', array_merge(
            [
                'mode' => 'edit',
                'branch' => $this->present($branch, app()->getLocale()),
                'hours' => app(WorkingHoursService::class)->branchHoursPayload($branch->id),
            ],
            $this->formSupport($request),
        ));
    }

    /** Shared select/options + permission flags for the create & edit pages. */
    protected function formSupport(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'partners' => Partner::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => (string) $p->getTranslation('name', $locale)])->all(),
            'cities' => City::query()->orderBy("name->{$locale}")->get(['id', 'name'])
                ->map(fn ($c) => ['id' => $c->id, 'name' => (string) $c->getTranslation('name', $locale)])->all(),
            'accounts' => Account::postableOptions([Account::TYPE_ASSET]),
            'can_edit_accounting' => (bool) $request->user()?->can('update_accounting_accounts'),
        ];
    }

    /**
     * Set the branch's cash/operating account link — only an accountant
     * (update_accounting_accounts) may change it; other admins leave it as-is.
     * Returns true when the link actually changed.
     */
    protected function applyAccountLink(Branch $branch, Request $request, array $data): bool
    {
        if (! $request->user()?->can('update_accounting_accounts')) {
            return false;
        }
        $branch->account_id = $data['account_id'] ?: null;

        return $branch->isDirty('account_id');
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
            'account_id' => ['nullable', 'integer', 'exists:chart_of_accounts,id'],

            // Weekly opening hours. Every day is submitted; `is_open` decides
            // whether the times matter.
            'hours' => ['required', 'array', 'size:7'],
            'hours.*.day' => ['required', 'integer', 'between:0,6'],
            'hours.*.is_open' => ['required', 'boolean'],
            'hours.*.open_at' => ['required', 'date_format:H:i'],
            'hours.*.close_at' => ['required', 'date_format:H:i'],
            'slot_length_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'slot_step_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'lead_time_minutes' => ['required', 'integer', 'min:0', 'max:10080'],
        ], [], [
            'slot_length_minutes' => 'appointment length',
            'slot_step_minutes' => 'slot interval',
            'lead_time_minutes' => 'minimum notice',
        ]);
    }

    /**
     * Opening/closing times only make sense against each other, so they're
     * checked after the field-level rules pass.
     */
    protected function validateHours(Request $request, array $data): void
    {
        $svc = app(WorkingHoursService::class);
        $errors = [];

        foreach ($data['hours'] as $i => $row) {
            if (! $row['is_open']) {
                continue;
            }
            $open = $svc->toMinutes($row['open_at']);
            $close = $svc->toMinutes($row['close_at']);
            $day = $svc->dayName((int) $row['day']);

            // close == open is the only genuinely meaningless case: a window of
            // exactly zero (or exactly 24h, indistinguishable here). close <
            // open is a legitimate overnight window.
            if ($open === $close) {
                $errors["hours.{$i}.close_at"] = "{$day}: closing time must differ from the opening time.";

                continue;
            }
            $span = $close > $open ? $close - $open : ($close + 1440) - $open;
            if ($span < $data['slot_length_minutes']) {
                $errors["hours.{$i}.close_at"] = "{$day}: the open window ({$span} min) is shorter than one appointment ({$data['slot_length_minutes']} min).";
            }
        }

        if ($data['slot_step_minutes'] > $data['slot_length_minutes']) {
            $errors['slot_step_minutes'] = 'The slot interval cannot be longer than an appointment.';
        }

        if (! collect($data['hours'])->contains(fn ($r) => $r['is_open'])) {
            $errors['hours.0.is_open'] = 'The branch has to be open at least one day a week.';
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }
    }

    /** Persist hours and turn the knock-on effects into a readable flash. */
    protected function saveHours(Branch $branch, array $data): array
    {
        $impact = app(WorkingHoursService::class)->saveBranchHours($branch, $data['hours'], [
            'slot_length_minutes' => $data['slot_length_minutes'],
            'slot_step_minutes' => $data['slot_step_minutes'],
            'lead_time_minutes' => $data['lead_time_minutes'],
        ]);

        $notes = [];
        if ($impact['adjusted_doctors']) {
            $notes[] = 'Working hours trimmed to fit for: '.implode(', ', $impact['adjusted_doctors']).'.';
        }
        if ($impact['closed_out_doctors']) {
            $notes[] = 'No longer scheduled (branch now closed on their days): '.implode(', ', $impact['closed_out_doctors']).'.';
        }
        if ($impact['affected_bookings'] > 0) {
            $notes[] = $impact['affected_bookings'].' upcoming booking(s) now fall outside these hours — review them on the bookings page.';
        }

        return $notes;
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
            'account_id' => $b->account_id,
            'account_label' => $b->account ? $b->account->code.' — '.$b->account->name : null,
        ];
    }
}
