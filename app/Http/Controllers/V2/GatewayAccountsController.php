<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Gateway;
use App\Models\GatewayAccount;
use App\Models\Partner;
use App\Models\Service;
use App\Support\ResolvesAccessibleClinics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Gateway Accounts — v2 replacement for the Filament GatewayAccountResource.
 *
 * Payment endpoints the booking flow can offer: either a "manual / POS" method
 * (cash, KNET, card, link) or an online gateway account (MyFatoorah, etc.).
 * `kind` and `method` live inside the credentials JSON; the rest of the JSON is
 * free-form (api_key, mode, …) edited as key/value pairs. Admin-only.
 */
class GatewayAccountsController extends Controller
{
    use ResolvesAccessibleClinics;

    protected const MANUAL_METHODS = ['cash' => 'Cash', 'knet' => 'KNET (POS)', 'visa' => 'Credit Card (POS)', 'link' => 'Payment Link (Online)'];

    protected const OWNER_TYPES = ['system', 'partner', 'branch', 'service'];

    protected function authorizeAccess(Request $request): void
    {
        $u = $request->user();
        if (! $u || ! $u->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Only admins can manage gateway accounts.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $locale = app()->getLocale();

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'kind' => $request->input('kind', 'all'),
            'owner_type' => $request->input('owner_type', 'all'),
        ];

        $query = GatewayAccount::query()->with(['gateway', 'partner:id,name', 'branch:id,name', 'service:id,name']);

        if ($filters['q'] !== '') {
            $query->where('display_name', 'like', "%{$filters['q']}%");
        }
        if (in_array($filters['kind'], ['manual', 'gateway'], true)) {
            $query->where('credentials->kind', $filters['kind']);
        }
        if (in_array($filters['owner_type'], self::OWNER_TYPES, true)) {
            $query->where('owner_type', $filters['owner_type']);
        }

        $page = $query->orderByDesc('updated_at')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (GatewayAccount $g) => $this->present($g, $locale));

        return Inertia::render('GatewayAccounts/Index', [
            'filters' => $filters,
            'page' => $page,
            'manualMethods' => collect(self::MANUAL_METHODS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'gateways' => Gateway::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn ($g) => ['id' => $g->id, 'name' => $g->label() ?: ($g->driver ?? ('#'.$g->id))])->all(),
            'partners' => Partner::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => (string) $p->getTranslation('name', $locale)])->all(),
            'branches' => $this->accessibleBranches()->all(),
            'services' => Service::query()->orderBy('id')->get(['id', 'name'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => (string) $s->getTranslation('name', $locale)])->all(),
            'counts' => [
                'total' => GatewayAccount::query()->count(),
                'active' => GatewayAccount::query()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateData($request);

        $account = new GatewayAccount();
        $this->fillFromData($account, $data);
        $account->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Gateway account created.']);
    }

    public function update(Request $request, GatewayAccount $gatewayAccount): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateData($request);

        $this->fillFromData($gatewayAccount, $data);
        $gatewayAccount->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Gateway account updated.']);
    }

    public function destroy(Request $request, GatewayAccount $gatewayAccount): RedirectResponse
    {
        $this->authorizeAccess($request);
        $gatewayAccount->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Gateway account deleted.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', 'in:manual,gateway'],
            'method' => ['nullable', 'required_if:kind,manual', 'in:cash,knet,visa,link'],
            'gateway_id' => ['nullable', 'required_if:kind,gateway', 'integer', 'exists:gateways,id'],
            'display_name' => ['required', 'string', 'max:120'],
            'currency' => ['required', 'string', 'max:8'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'owner_type' => ['required', 'in:system,partner,branch,service'],
            'partner_id' => ['nullable', 'required_if:owner_type,partner', 'integer', 'exists:partners,id'],
            'branch_id' => ['nullable', 'required_if:owner_type,branch', 'integer', 'exists:branches,id'],
            'service_id' => ['nullable', 'required_if:owner_type,service', 'integer', 'exists:services,id'],
            'extra_credentials' => ['array'],
            'extra_credentials.*.key' => ['nullable', 'string', 'max:191'],
            'extra_credentials.*.value' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    protected function fillFromData(GatewayAccount $account, array $data): void
    {
        // Rebuild the credentials JSON: kind + (method) + free-form extra pairs.
        $credentials = ['kind' => $data['kind']];
        if ($data['kind'] === 'manual') {
            $credentials['method'] = $data['method'];
        }
        foreach ($data['extra_credentials'] ?? [] as $pair) {
            $key = trim((string) ($pair['key'] ?? ''));
            if ($key !== '' && ! in_array($key, ['kind', 'method'], true)) {
                $credentials[$key] = $pair['value'] ?? '';
            }
        }

        $account->credentials = $credentials;
        $account->gateway_id = $data['kind'] === 'gateway' ? $data['gateway_id'] : null;
        $account->display_name = $data['display_name'];
        $account->currency = $data['currency'];
        $account->is_active = (bool) ($data['is_active'] ?? false);
        $account->is_default = (bool) ($data['is_default'] ?? false);
        $account->owner_type = $data['owner_type'];
        $account->partner_id = $data['owner_type'] === 'partner' ? $data['partner_id'] : null;
        $account->branch_id = $data['owner_type'] === 'branch' ? $data['branch_id'] : null;
        $account->service_id = $data['owner_type'] === 'service' ? $data['service_id'] : null;
    }

    protected function present(GatewayAccount $g, string $locale): array
    {
        $cred = is_array($g->credentials) ? $g->credentials : [];
        $kind = (string) ($cred['kind'] ?? 'gateway');

        // Free-form credential pairs minus the managed keys.
        $extra = collect($cred)->except(['kind', 'method'])
            ->map(fn ($v, $k) => ['key' => $k, 'value' => is_scalar($v) ? (string) $v : json_encode($v)])->values()->all();

        $ownerName = match ($g->owner_type) {
            'partner' => $g->partner ? (string) $g->partner->getTranslation('name', $locale) : null,
            'branch' => $g->branch?->localized_name,
            'service' => $g->service ? (string) $g->service->getTranslation('name', $locale) : null,
            default => null,
        };

        return [
            'id' => $g->id,
            'display_name' => $g->display_name,
            'kind' => $kind,
            'method' => $kind === 'manual' ? (string) ($cred['method'] ?? '') : null,
            'gateway_id' => $g->gateway_id,
            'gateway_name' => $g->gateway ? ($g->gateway->label() ?: $g->gateway->driver) : null,
            'currency' => $g->currency,
            'is_active' => (bool) $g->is_active,
            'is_default' => (bool) $g->is_default,
            'owner_type' => $g->owner_type,
            'owner_name' => $ownerName,
            'partner_id' => $g->partner_id,
            'branch_id' => $g->branch_id,
            'service_id' => $g->service_id,
            'extra_credentials' => $extra,
        ];
    }
}
