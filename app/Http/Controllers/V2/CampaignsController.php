<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Jobs\SendCampaignInvite;
use App\Models\BulkInviteCampaign;
use App\Models\BulkInviteCampaignRecipient;
use App\Services\WhatsAppTemplateCatalog;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bulk Invite Campaigns — v2 replacement for the Filament
 * BulkInviteCampaignResource. Pick an approved Meta template, fill its
 * variables, attach recipients, then test-send or validate-and-queue. The
 * heavy lifting (template catalog, phone parsing, send job) is reused as-is.
 * Admin-only.
 */
class CampaignsController extends Controller
{
    protected const REGIONS = ['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'EG'];

    public function __construct(protected WhatsAppTemplateCatalog $catalog) {}

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_bulk_invite_campaigns')) {
            abort(403, 'Admin access required.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = ['q' => trim((string) $request->input('q', '')), 'status' => $request->input('status', 'all')];

        $query = BulkInviteCampaign::query();
        if ($filters['q'] !== '') {
            $query->where('name', 'like', "%{$filters['q']}%");
        }
        if (in_array($filters['status'], ['draft', 'scheduled', 'running', 'paused', 'completed', 'failed'], true)) {
            $query->where('status', $filters['status']);
        }

        $page = $query->withCount('recipients')->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $page->getCollection()->transform(fn (BulkInviteCampaign $c) => $this->present($c));

        return Inertia::render('Campaigns/Index', [
            'filters' => $filters,
            'page' => $page,
            'templates' => $this->templateOptions(),
            'regions' => self::REGIONS,
            'counts' => [
                'total' => BulkInviteCampaign::query()->count(),
                'running' => BulkInviteCampaign::query()->whereIn('status', ['running', 'scheduled'])->count(),
            ],
        ]);
    }

    public function show(Request $request, BulkInviteCampaign $campaign): Response
    {
        $this->authorizeAccess($request);

        $recipients = $campaign->recipients()->orderByDesc('id')->limit(500)->get()
            ->map(fn (BulkInviteCampaignRecipient $r) => [
                'id' => $r->id, 'msisdn' => $r->msisdn, 'name' => $r->name, 'locale' => $r->locale,
                'source' => $r->source, 'status' => $r->status, 'error_message' => $r->error_message,
            ])->all();

        return Inertia::render('Campaigns/Show', [
            'campaign' => array_merge($this->present($campaign), [
                'template_variables' => $campaign->template_variables ?: [],
                'default_locale' => $campaign->default_locale,
                'scheduled_at' => optional($campaign->scheduled_at)->format('Y-m-d\TH:i'),
                'send_rate_per_min' => $campaign->send_rate_per_min,
            ]),
            'templateDef' => $this->templateDefFor($campaign),
            'templates' => $this->templateOptions(),
            'recipients' => $recipients,
            'regions' => self::REGIONS,
            'statusCounts' => $campaign->recipients()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateData($request);

        $campaign = new BulkInviteCampaign(['status' => 'draft']);
        $this->fill($campaign, $data);
        $campaign->save();

        return redirect()->route('v2.campaigns.show', $campaign)->with('flash', ['type' => 'success', 'message' => 'Campaign created.']);
    }

    public function update(Request $request, BulkInviteCampaign $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateData($request);

        $this->fill($campaign, $data);
        $campaign->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Campaign updated.']);
    }

    public function destroy(Request $request, BulkInviteCampaign $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $campaign->recipients()->delete();
        $campaign->delete();

        return redirect()->route('v2.campaigns.index')->with('flash', ['type' => 'success', 'message' => 'Campaign deleted.']);
    }

    // ---- recipients ----

    public function addRecipients(Request $request, BulkInviteCampaign $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'numbers' => ['required', 'string', 'max:100000'],
            'preferred_region' => ['required', 'in:'.implode(',', self::REGIONS)],
            'name' => ['nullable', 'string', 'max:191'],
            'locale' => ['nullable', 'in:en,ar'],
        ]);

        $lines = preg_split('/[\r\n,;]+/', $data['numbers']);
        $added = 0; $skipped = 0;
        foreach ($lines as $line) {
            $raw = trim((string) $line);
            if ($raw === '') {
                continue;
            }
            $e164 = Phone::parseToE164AcrossRegions($raw, self::REGIONS, $data['preferred_region'], true);
            if (! $e164) {
                $skipped++;

                continue;
            }
            $r = BulkInviteCampaignRecipient::firstOrNew(['bulk_invite_campaign_id' => $campaign->id, 'msisdn' => $e164]);
            $r->fill(['name' => $data['name'] ?? $r->name, 'locale' => $data['locale'] ?? ($r->locale ?? $campaign->default_locale ?? 'en'), 'source' => 'paste', 'status' => $r->status ?: 'pending']);
            $wasNew = ! $r->exists;
            $r->save();
            $added += $wasNew ? 1 : 0;
        }

        $campaign->update(['total_recipients' => $campaign->recipients()->count()]);

        return back()->with('flash', ['type' => 'success', 'message' => "Added {$added} recipient(s)".($skipped ? ", skipped {$skipped} invalid" : '').'.']);
    }

    public function deleteRecipient(Request $request, BulkInviteCampaign $campaign, BulkInviteCampaignRecipient $recipient): RedirectResponse
    {
        $this->authorizeAccess($request);
        abort_unless($recipient->bulk_invite_campaign_id === $campaign->id, 404);
        $recipient->delete();
        $campaign->update(['total_recipients' => $campaign->recipients()->count()]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Recipient removed.']);
    }

    // ---- send actions ----

    public function sendTest(Request $request, BulkInviteCampaign $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'test_msisdn' => ['required', 'string', 'max:32'],
            'preferred_region' => ['required', 'in:'.implode(',', self::REGIONS)],
        ]);

        $e164 = Phone::parseToE164AcrossRegions($data['test_msisdn'], self::REGIONS, $data['preferred_region'], true);
        if (! $e164) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Invalid mobile number for the supported regions.']);
        }

        $recipient = BulkInviteCampaignRecipient::firstOrNew(['bulk_invite_campaign_id' => $campaign->id, 'msisdn' => $e164], ['status' => 'pending']);
        $recipient->fill(['status' => 'pending', 'error_message' => null, 'wa_message_id' => null, 'source' => $recipient->source ?: 'test'])->save();

        dispatch(new SendCampaignInvite($campaign->id, $recipient->id));

        return back()->with('flash', ['type' => 'success', 'message' => "Test invite queued for {$e164}."]);
    }

    public function queue(Request $request, BulkInviteCampaign $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);

        if (! $campaign->template_name || ! $campaign->template_details) {
            return back()->with('flash', ['type' => 'error', 'message' => 'A valid template must be selected first.']);
        }
        if (in_array($campaign->status, ['running', 'completed'], true)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Campaign is already running or completed.']);
        }

        $components = $campaign->template_details['components'] ?? [];
        $header = collect($components)->firstWhere('type', 'HEADER');
        if ($header && strtoupper((string) ($header['format'] ?? '')) === 'IMAGE' && ! $campaign->header_image_path) {
            return back()->with('flash', ['type' => 'error', 'message' => 'This template needs a header image, but none is set.']);
        }

        $body = collect($components)->firstWhere('type', 'BODY');
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string) data_get($body, 'text', ''), $m);
        $indexes = collect($m[1] ?? [])->map(fn ($i) => (string) ((int) $i))->unique();
        foreach ($indexes as $i) {
            if (empty(Arr::get($campaign->template_variables ?? [], $i))) {
                return back()->with('flash', ['type' => 'error', 'message' => "Template variable {{$i}} is required but empty."]);
            }
        }

        $ids = $campaign->recipients()->whereIn('status', ['pending', 'failed'])->pluck('id');
        if ($campaign->recipients()->count() === 0) {
            return back()->with('flash', ['type' => 'error', 'message' => 'No recipients yet. Add some first.']);
        }
        if ($ids->isEmpty()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'No pending/failed recipients to send to.']);
        }

        try {
            DB::transaction(function () use ($campaign, $ids) {
                $campaign->status = $campaign->scheduled_at ? 'scheduled' : 'running';
                $campaign->save();
                $campaign->recipients()->whereIn('id', $ids)->update(['status' => 'pending', 'error_message' => null, 'wa_message_id' => null]);
            });
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Could not queue: '.$e->getMessage()]);
        }

        foreach ($ids as $id) {
            dispatch(new SendCampaignInvite($campaign->id, $id));
        }

        return back()->with('flash', ['type' => 'success', 'message' => "Queued {$ids->count()} recipient(s). Status: {$campaign->status}."]);
    }

    // ---- helpers ----

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'template_name' => ['required', 'string', 'max:191'],
            'template_variables' => ['array'],
            'scheduled_at' => ['nullable', 'date'],
            'send_rate_per_min' => ['required', 'integer', 'min:60', 'max:10000'],
            'header_image_path' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    protected function fill(BulkInviteCampaign $campaign, array $data): void
    {
        $campaign->name = $data['name'];
        $campaign->template_name = $data['template_name'];
        $campaign->scheduled_at = $data['scheduled_at'] ?? null;
        $campaign->send_rate_per_min = $data['send_rate_per_min'];
        if (array_key_exists('header_image_path', $data)) {
            $campaign->header_image_path = $data['header_image_path'] ?: $campaign->header_image_path;
        }

        // Resolve the template definition from the catalog (best-effort).
        $details = null;
        try {
            $details = $this->catalog->find($data['template_name']);
        } catch (\Throwable) {
            $details = null;
        }
        if ($details) {
            $campaign->template_details = $details;
            $campaign->default_locale = (string) data_get($details, 'language', $campaign->default_locale ?? 'en');
        }

        // Keep only the variable keys this template actually needs.
        $campaign->template_variables = $data['template_variables'] ?? ($campaign->template_variables ?? []);
    }

    /** Derive the variable/header shape for a campaign's stored template details. */
    protected function templateDefFor(BulkInviteCampaign $campaign): ?array
    {
        $details = $campaign->template_details;
        if (! is_array($details) || empty($details)) {
            return null;
        }
        $components = $details['components'] ?? [];
        $body = collect($components)->firstWhere('type', 'BODY');
        $header = collect($components)->firstWhere('type', 'HEADER');
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string) data_get($body, 'text', ''), $m);
        $indexes = collect($m[1] ?? [])->map(fn ($i) => (string) ((int) $i))->unique()->sort()->values()->all();

        return [
            'name' => $campaign->template_name,
            'language' => (string) data_get($details, 'language', $campaign->default_locale ?? 'en'),
            'body_text' => (string) data_get($body, 'text', ''),
            'header_format' => $header ? strtoupper((string) ($header['format'] ?? '')) : null,
            'var_indexes' => $indexes,
        ];
    }

    /** @return array<int, array{name:string,label:string,language:string,header_format:?string,var_indexes:array}> */
    protected function templateOptions(): array
    {
        try {
            $all = $this->catalog->all();
        } catch (\Throwable) {
            return [];
        }

        return collect($all)->map(function ($tpl) {
            $components = $tpl['components'] ?? [];
            $body = collect($components)->firstWhere('type', 'BODY');
            $header = collect($components)->firstWhere('type', 'HEADER');
            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string) data_get($body, 'text', ''), $m);
            $indexes = collect($m[1] ?? [])->map(fn ($i) => (string) ((int) $i))->unique()->sort()->values()->all();

            return [
                'name' => $tpl['name'] ?? '',
                'label' => ($tpl['name'] ?? '').' ('.($tpl['language'] ?? 'lang').')',
                'language' => $tpl['language'] ?? 'en',
                'body_text' => (string) data_get($body, 'text', ''),
                'header_format' => $header ? strtoupper((string) ($header['format'] ?? '')) : null,
                'var_indexes' => $indexes,
            ];
        })->values()->all();
    }

    protected function present(BulkInviteCampaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'template_name' => $c->template_name,
            'default_locale' => $c->default_locale,
            'status' => $c->status,
            'total_recipients' => $c->total_recipients ?? ($c->recipients_count ?? 0),
            'sent_count' => $c->sent_count ?? 0,
            'failed_count' => $c->failed_count ?? 0,
            'scheduled_at_label' => optional($c->scheduled_at)->format('Y-m-d h:i A'),
            'updated_at' => optional($c->updated_at)->diffForHumans(),
            'header_image_path' => $c->header_image_path,
            'has_template_details' => ! empty($c->template_details),
        ];
    }
}
