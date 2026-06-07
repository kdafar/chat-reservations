<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Wa\Hub\Models\Contact;
use App\Wa\Hub\Models\ContactEngagementStat;
use App\Wa\Hub\Models\ContactGroup;
use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Hub\Models\PromotionalCampaign;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Jobs\ImportBulkInviteRecipients;
use App\Wa\Jobs\SendPromotionalCampaignMessage;
use App\Wa\Models\WhatsApp\WaAccount;
use App\Wa\Models\WhatsApp\WaContact;
use App\Wa\Models\WhatsApp\WaConversation;
use App\Wa\Models\WhatsApp\WaCredential;
use App\Wa\Models\WhatsApp\WaMessage;
use App\Wa\Models\WhatsApp\WaNumber;
use App\Wa\Services\WhatsApp\WhatsAppService;
use App\Wa\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * v2 (Inertia/Vue) admin surface for the isolated WhatsApp module (app/Wa).
 *
 * Reads/writes the module's own data on the `wa` connection (wam_ tables) and
 * renders native v2 pages under resources/js/v2/Pages/WaModule. Kept separate
 * from the clinic's existing v2 WhatsApp controllers (which drive clinic tables).
 */
class WaModuleController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRole(['admin', 'super_admin', 'clinic_admin'])) {
            abort(403, 'Admin access required.');
        }
    }

    /** Overview dashboard with live module counts. */
    public function dashboard(Request $request)
    {
        $this->authorizeAccess($request);

        $stats = [
            'templates' => MessageTemplate::count(),
            'templates_approved' => MessageTemplate::where('status', 'APPROVED')->count(),
            'contacts' => Contact::count(),
            'contact_groups' => ContactGroup::count(),
            'campaigns' => PromotionalCampaign::count(),
            'conversations' => WaConversation::count(),
            'sessions' => WhatsappSession::count(),
            'messages' => WaMessage::count(),
            'messages_in' => WaMessage::where('direction', 'inbound')->count(),
            'messages_out' => WaMessage::where('direction', 'outbound')->count(),
        ];

        $recent = WaMessage::query()
            ->latest('created_at')
            ->limit(15)
            ->get()
            ->map(fn (WaMessage $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'type' => $m->type,
                'body' => \Illuminate\Support\Str::limit((string) $m->body, 80),
                'status' => $m->status,
                'created_at' => optional($m->created_at)->toDateTimeString(),
            ]);

        return Inertia::render('WaModule/Dashboard', [
            'stats' => $stats,
            'recent' => $recent,
            'configured' => (bool) config('services.whatsapp.api_token'),
            'panel_url' => url('/whatsapp/admin'),
        ]);
    }

    /** Message templates list. */
    public function templates(Request $request)
    {
        $this->authorizeAccess($request);

        $filters = ['q' => trim((string) $request->query('q', ''))];

        $page = MessageTemplate::query()
            ->when($filters['q'] !== '', fn ($q) => $q->where('name', 'like', "%{$filters['q']}%"))
            ->latest('updated_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (MessageTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'category' => $t->category,
                'language' => $t->language,
                'status' => $t->status,
                'local_status' => $t->local_status,
                'is_auto_reply' => (bool) $t->is_auto_reply,
                ...$this->templateParts($t),
                'body' => $t->body,
                'triggers' => $t->triggers ?? [],
                'meta_id' => $t->meta_id,
                'body_preview' => \Illuminate\Support\Str::limit((string) ($t->body_preview ?: $t->body), 120),
                'updated_at' => optional($t->updated_at)->toDateTimeString(),
            ]);

        return Inertia::render('WaModule/Templates', [
            'filters' => $filters,
            'page' => $page,
            'can_edit' => true,
        ]);
    }

    /** Create a local message template (optionally publish to Meta). */
    public function storeTemplate(Request $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateTemplate($request);

        $tpl = MessageTemplate::create($data + ['local_status' => 'draft']);

        if ($request->boolean('publish')) {
            try {
                $whatsapp->publishTemplateToMeta($tpl);
                return back()->with('flash', ['type' => 'success', 'message' => 'Template created and submitted to Meta for review.']);
            } catch (\Throwable $e) {
                return back()->with('flash', ['type' => 'error', 'message' => 'Saved locally, but Meta submit failed: '.$e->getMessage()]);
            }
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Template saved as draft.']);
    }

    /** Update a local template (blocked once approved by Meta). */
    public function updateTemplate(Request $request, int $template): RedirectResponse
    {
        $this->authorizeAccess($request);
        $tpl = MessageTemplate::findOrFail($template);

        if ($tpl->status === 'APPROVED') {
            return back()->with('flash', ['type' => 'error', 'message' => 'Approved templates cannot be edited.']);
        }

        $tpl->update($this->validateTemplate($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Template updated.']);
    }

    public function destroyTemplate(Request $request, int $template): RedirectResponse
    {
        $this->authorizeAccess($request);
        MessageTemplate::whereKey($template)->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Template deleted.']);
    }

    /** Create a CAROUSEL template (multi-card: image + body + buttons per card). */
    public function storeCarousel(Request $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);
        $v = $request->validate([
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'category' => ['required', 'in:MARKETING,UTILITY'],
            'language' => ['required', 'in:en,ar'],
            'body' => ['required', 'string', 'max:1024'],
            'cards' => ['required', 'array', 'min:2', 'max:10'],
            'cards.*.image_url' => ['required', 'url', 'max:2048'],
            'cards.*.body' => ['required', 'string', 'max:160'],
            'cards.*.buttons' => ['array', 'max:2'],
            'cards.*.buttons.*.type' => ['required_with:cards.*.buttons', 'in:QUICK_REPLY,URL'],
            'cards.*.buttons.*.text' => ['required_with:cards.*.buttons', 'string', 'max:25'],
            'cards.*.buttons.*.url' => ['nullable', 'string', 'max:2048'],
            'publish' => ['boolean'],
        ]);

        $cards = array_map(function ($card) {
            $comps = [
                ['type' => 'HEADER', 'format' => 'IMAGE', 'example' => ['header_handle' => [$card['image_url']]], 'media_url' => $card['image_url']],
                ['type' => 'BODY', 'text' => $card['body']],
            ];
            if (! empty($card['buttons'])) {
                $comps[] = ['type' => 'BUTTONS', 'buttons' => array_map(function ($b) {
                    $btn = ['type' => $b['type'], 'text' => $b['text']];
                    if ($b['type'] === 'URL' && ! empty($b['url'])) {
                        $btn['url'] = $b['url'];
                    }
                    return $btn;
                }, $card['buttons'])];
            }
            return ['components' => $comps];
        }, $v['cards']);

        $components = [
            ['type' => 'BODY', 'text' => $v['body']],
            ['type' => 'CAROUSEL', 'cards' => $cards],
        ];

        $tpl = MessageTemplate::create([
            'name' => strtolower($v['name']),
            'category' => $v['category'],
            'language' => $v['language'],
            'body' => $v['body'],
            'body_preview' => \Illuminate\Support\Str::limit($v['body'], 160).' · 🎠 '.count($cards).' cards',
            'components' => $components,
            'local_status' => 'draft',
            'is_auto_reply' => false,
            'triggers' => [],
        ]);

        if ($request->boolean('publish')) {
            try {
                $whatsapp->publishTemplateToMeta($tpl);
                return back()->with('flash', ['type' => 'success', 'message' => 'Carousel created and submitted to Meta.']);
            } catch (\Throwable $e) {
                return back()->with('flash', ['type' => 'error', 'message' => 'Saved locally, Meta submit failed: '.$e->getMessage()]);
            }
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Carousel template saved as draft.']);
    }

    /** Pull all templates + statuses from Meta into the local table. */
    public function syncTemplates(Request $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);
        try {
            $count = $whatsapp->syncTemplatesFromMeta();
            return back()->with('flash', ['type' => 'success', 'message' => "Synced {$count} templates from Meta."]);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Sync failed: '.$e->getMessage()]);
        }
    }

    /** Submit a local draft to Meta for approval. */
    public function publishTemplate(Request $request, int $template, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);
        $tpl = MessageTemplate::findOrFail($template);
        try {
            $whatsapp->publishTemplateToMeta($tpl);
            return back()->with('flash', ['type' => 'success', 'message' => 'Submitted to Meta for review.']);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Submit failed: '.$e->getMessage()]);
        }
    }

    public function toggleTemplateAutoReply(Request $request, int $template): RedirectResponse
    {
        $this->authorizeAccess($request);
        $tpl = MessageTemplate::findOrFail($template);
        $tpl->update(['is_auto_reply' => ! $tpl->is_auto_reply]);

        return back()->with('flash', ['type' => 'success', 'message' => $tpl->is_auto_reply ? 'Auto-reply enabled.' : 'Auto-reply disabled.']);
    }

    private function validateTemplate(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'category' => ['required', 'in:MARKETING,UTILITY,AUTHENTICATION'],
            'language' => ['required', 'in:en,ar'],
            'header_type' => ['nullable', 'in:NONE,TEXT,IMAGE,VIDEO,DOCUMENT'],
            'header_text' => ['nullable', 'string', 'max:60'],
            'header_media_url' => ['nullable', 'url', 'max:2048'],
            'body' => ['required', 'string', 'max:1024'],
            'footer_text' => ['nullable', 'string', 'max:60'],
            'is_auto_reply' => ['boolean'],
            'triggers' => ['array'],
            'triggers.*' => ['string', 'max:60'],
            'buttons' => ['array', 'max:10'],
            'buttons.*.type' => ['required_with:buttons', 'in:QUICK_REPLY,URL,PHONE_NUMBER'],
            'buttons.*.text' => ['required_with:buttons', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2048'],
            'buttons.*.phone_number' => ['nullable', 'string', 'max:20'],
        ]);

        // header_type/header_text/footer/buttons are not columns — they live
        // inside the Meta `components` structure. Build that here.
        $components = [];
        if (($v['header_type'] ?? 'NONE') !== 'NONE') {
            $header = ['type' => 'HEADER', 'format' => $v['header_type']];
            if ($v['header_type'] === 'TEXT') {
                $header['text'] = $v['header_text'] ?? null;
            } elseif (! empty($v['header_media_url'])) {
                $header['example'] = ['header_handle' => [$v['header_media_url']]];
                $header['media_url'] = $v['header_media_url'];
            }
            $components[] = array_filter($header);
        }
        $components[] = ['type' => 'BODY', 'text' => $v['body']];
        if (! empty($v['footer_text'])) {
            $components[] = ['type' => 'FOOTER', 'text' => $v['footer_text']];
        }
        if (! empty($v['buttons'])) {
            $buttons = array_map(function ($b) {
                $btn = ['type' => $b['type'], 'text' => $b['text']];
                if ($b['type'] === 'URL' && ! empty($b['url'])) {
                    $btn['url'] = $b['url'];
                }
                if ($b['type'] === 'PHONE_NUMBER' && ! empty($b['phone_number'])) {
                    $btn['phone_number'] = $b['phone_number'];
                }
                return $btn;
            }, $v['buttons']);
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return [
            'name' => strtolower($v['name']),
            'category' => $v['category'],
            'language' => $v['language'],
            'body' => $v['body'],
            'body_preview' => \Illuminate\Support\Str::limit($v['body'], 160),
            'components' => $components,
            'is_auto_reply' => (bool) ($v['is_auto_reply'] ?? false),
            'triggers' => $v['triggers'] ?? [],
        ];
    }

    /** Pull header/footer/buttons back out of the components JSON for edit prefill. */
    private function templateParts(MessageTemplate $t): array
    {
        $components = is_array($t->components) ? $t->components : [];
        $byType = fn ($type) => collect($components)->first(fn ($c) => strtoupper($c['type'] ?? '') === $type);
        $header = $byType('HEADER');
        $footer = $byType('FOOTER');
        $buttonsC = $byType('BUTTONS');

        return [
            'header_type' => $header['format'] ?? 'NONE',
            'header_text' => $header['text'] ?? null,
            'header_media_url' => $header['media_url'] ?? ($header['example']['header_handle'][0] ?? null),
            'footer_text' => $footer['text'] ?? null,
            'buttons' => collect($buttonsC['buttons'] ?? [])->map(fn ($b) => [
                'type' => $b['type'] ?? 'QUICK_REPLY',
                'text' => $b['text'] ?? '',
                'url' => $b['url'] ?? '',
                'phone_number' => $b['phone_number'] ?? '',
            ])->values()->all(),
        ];
    }

    /** Contacts + groups. */
    public function contacts(Request $request)
    {
        $this->authorizeAccess($request);

        $filters = ['q' => trim((string) $request->query('q', ''))];

        $page = Contact::query()
            ->with(['groups:id', 'engagementStat'])
            ->when($filters['q'] !== '', fn ($q) => $q
                ->where(fn ($w) => $w->where('msisdn', 'like', "%{$filters['q']}%")->orWhere('name', 'like', "%{$filters['q']}%")))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Contact $c) => [
                'id' => $c->id,
                'msisdn' => $c->msisdn,
                'name' => $c->name,
                'locale' => $c->locale,
                'group_ids' => $c->groups->pluck('id'),
                'eng' => $c->engagementStat ? [
                    'sent' => $c->engagementStat->sent_count,
                    'delivered' => $c->engagementStat->delivered_count,
                    'read' => $c->engagementStat->read_count,
                    'failed' => $c->engagementStat->failed_count,
                    'active' => (bool) $c->engagementStat->is_active,
                ] : null,
                'created_at' => optional($c->created_at)->toDateTimeString(),
            ]);

        $groups = ContactGroup::query()
            ->withCount('contacts')
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->map(fn (ContactGroup $g) => [
                'id' => $g->id,
                'name' => $g->name,
                'group_type' => $g->group_type,
                'contacts_count' => $g->contacts_count,
            ]);

        return Inertia::render('WaModule/Contacts', [
            'filters' => $filters,
            'page' => $page,
            'groups' => $groups,
            'can_edit' => true,
        ]);
    }

    public function storeContact(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'msisdn' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:191'],
            'locale' => ['nullable', 'in:en,ar'],
        ]);
        Contact::updateOrCreate(['msisdn' => $data['msisdn']], [
            'name' => $data['name'] ?? null,
            'locale' => $data['locale'] ?? 'en',
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Contact saved.']);
    }

    public function updateContact(Request $request, int $contact): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'msisdn' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:191'],
            'locale' => ['nullable', 'in:en,ar'],
        ]);
        Contact::whereKey($contact)->update($data);

        return back()->with('flash', ['type' => 'success', 'message' => 'Contact updated.']);
    }

    public function destroyContact(Request $request, int $contact): RedirectResponse
    {
        $this->authorizeAccess($request);
        Contact::whereKey($contact)->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Contact deleted.']);
    }

    public function storeGroup(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:500'],
            'group_type' => ['nullable', 'in:static,dynamic'],
        ]);
        ContactGroup::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'group_type' => $data['group_type'] ?? 'static',
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Group created.']);
    }

    public function destroyGroup(Request $request, int $group): RedirectResponse
    {
        $this->authorizeAccess($request);
        $g = ContactGroup::findOrFail($group);
        $g->contacts()->detach();
        $g->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Group deleted.']);
    }

    /** Add/remove a contact to/from a group. */
    public function toggleGroupMember(Request $request, int $group): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate(['contact_id' => ['required', 'integer']]);
        $g = ContactGroup::findOrFail($group);
        $res = $g->contacts()->toggle([$data['contact_id']]);
        $added = ! empty($res['attached']);

        return back()->with('flash', ['type' => 'success', 'message' => $added ? 'Added to group.' : 'Removed from group.']);
    }

    /** Promotional / bulk campaigns. */
    public function campaigns(Request $request)
    {
        $this->authorizeAccess($request);

        $page = PromotionalCampaign::query()
            ->withCount([
                'recipients',
                'recipients as pending_count' => fn ($q) => $q->where('status', 'pending'),
                'recipients as sent_count' => fn ($q) => $q->whereIn('status', ['sent', 'delivered', 'read']),
            ])
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PromotionalCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'template_name' => $c->template_name,
                'status' => $c->status,
                'recipients_count' => $c->recipients_count,
                'pending_count' => $c->pending_count,
                'sent_count' => $c->sent_count,
                'default_locale' => $c->default_locale,
                'send_rate_per_min' => $c->send_rate_per_min,
                'scheduled_at' => optional($c->scheduled_at)->format('Y-m-d\TH:i'),
                'sent_at' => optional($c->sent_at)->toDateTimeString(),
                'created_at' => optional($c->created_at)->toDateTimeString(),
            ]);

        $templates = MessageTemplate::query()
            ->orderBy('name')
            ->get()
            ->map(fn (MessageTemplate $t) => array_merge([
                'name' => $t->name,
                'language' => $t->language ?: 'en',
                'body' => $t->body,
                'var_count' => preg_match_all('/\{\{\s*\d+\s*\}\}/', (string) $t->body),
            ], $this->templateParts($t)))
            ->values();

        $groups = ContactGroup::query()->withCount('contacts')->orderBy('name')->get()
            ->map(fn (ContactGroup $g) => ['id' => $g->id, 'name' => $g->name, 'count' => $g->contacts_count]);

        return Inertia::render('WaModule/Campaigns', [
            'page' => $page,
            'templates' => $templates,
            'groups' => $groups,
            'can_edit' => true,
        ]);
    }

    /** Per-campaign analytics: funnel, rates, failure breakdown, latency, recipients. */
    public function campaignAnalytics(Request $request, int $campaign)
    {
        $this->authorizeAccess($request);
        $c = PromotionalCampaign::findOrFail($campaign);

        $base = PromotionalCampaignRecipient::where('promotional_campaign_id', $c->id);
        $total = (clone $base)->count();
        $byStatus = (clone $base)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status')->toArray();
        $g = fn ($k) => (int) ($byStatus[$k] ?? 0);

        $sent = $g('sent') + $g('delivered') + $g('read');
        $delivered = $g('delivered') + $g('read');
        $read = $g('read');
        $failed = $g('failed') + $g('limited') + $g('undeliverable') + $g('experiment_blocked');
        $pending = $g('pending');

        // latency (seconds) — MySQL TIMESTAMPDIFF
        $avgDeliver = (clone $base)->whereNotNull('delivered_at')->whereNotNull('sent_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, sent_at, delivered_at)) a')->value('a');
        $avgRead = (clone $base)->whereNotNull('read_at')->whereNotNull('sent_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, sent_at, read_at)) a')->value('a');

        $failures = (clone $base)->whereIn('status', ['failed', 'limited', 'undeliverable', 'experiment_blocked'])
            ->selectRaw('COALESCE(wa_error_title, error_message, "Unknown") as reason, wa_error_code as code, COUNT(*) as n')
            ->groupBy('reason', 'code')->orderByDesc('n')->limit(20)->get()
            ->map(fn ($r) => ['reason' => $r->reason, 'code' => $r->code, 'count' => $r->n]);

        $statusFilter = $request->query('status', 'all');
        $recipients = (clone $base)
            ->when($statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (PromotionalCampaignRecipient $r) => [
                'id' => $r->id,
                'msisdn' => $r->msisdn,
                'name' => $r->name,
                'status' => $r->status,
                'sent_at' => optional($r->sent_at)->format('H:i:s'),
                'delivered_at' => optional($r->delivered_at)->format('H:i:s'),
                'read_at' => optional($r->read_at)->format('H:i:s'),
                'error' => $r->wa_error_title ?: $r->error_message,
                'pricing' => $r->wa_pricing_model,
            ]);

        return Inertia::render('WaModule/CampaignAnalytics', [
            'campaign' => ['id' => $c->id, 'name' => $c->name, 'status' => $c->status, 'template_name' => $c->template_name],
            'metrics' => [
                'total' => $total,
                'sent' => $sent, 'delivered' => $delivered, 'read' => $read, 'failed' => $failed, 'pending' => $pending,
                'delivery_rate' => $sent ? round($delivered / $sent * 100, 1) : 0,
                'read_rate' => $delivered ? round($read / $delivered * 100, 1) : 0,
                'fail_rate' => $total ? round($failed / $total * 100, 1) : 0,
                'avg_deliver_sec' => $avgDeliver ? round($avgDeliver) : null,
                'avg_read_sec' => $avgRead ? round($avgRead) : null,
            ],
            'failures' => $failures,
            'recipients' => $recipients,
            'filters' => ['status' => $statusFilter],
        ]);
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateCampaign($request);
        PromotionalCampaign::create($data + ['status' => 'draft']);

        return back()->with('flash', ['type' => 'success', 'message' => 'Campaign created.']);
    }

    public function updateCampaign(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $c = PromotionalCampaign::findOrFail($campaign);
        if (in_array($c->status, ['sending', 'completed'], true)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'A sending/completed campaign cannot be edited.']);
        }
        $c->update($this->validateCampaign($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Campaign updated.']);
    }

    public function destroyCampaign(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $c = PromotionalCampaign::findOrFail($campaign);
        $c->recipients()->delete();
        $c->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Campaign deleted.']);
    }

    /** Dispatch send jobs for all pending recipients. */
    public function sendCampaign(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $c = PromotionalCampaign::findOrFail($campaign);

        if (! config('services.whatsapp.api_token')) {
            return back()->with('flash', ['type' => 'error', 'message' => 'WhatsApp is not configured.']);
        }

        $pending = $c->recipients()->where('status', 'pending')->pluck('id');
        if ($pending->isEmpty()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'No pending recipients. Add recipients first.']);
        }

        $c->update(['status' => 'sending', 'sent_at' => $c->sent_at ?? now()]);
        foreach ($pending as $rid) {
            SendPromotionalCampaignMessage::dispatch($c->id, $rid);
        }

        return back()->with('flash', ['type' => 'success', 'message' => "Queued {$pending->count()} messages."]);
    }

    /** Add a single recipient phone to a campaign (quick test / manual). */
    public function addCampaignRecipient(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'msisdn' => ['required', 'string', 'max:32'],
            'name' => ['nullable', 'string', 'max:191'],
        ]);
        $c = PromotionalCampaign::findOrFail($campaign);
        PromotionalCampaignRecipient::updateOrCreate(
            ['promotional_campaign_id' => $c->id, 'msisdn' => $data['msisdn']],
            ['name' => $data['name'] ?? null, 'status' => 'pending', 'locale' => $c->default_locale ?? 'en', 'source' => 'manual']
        );
        $c->update(['total_recipients' => $c->recipients()->count()]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Recipient added.']);
    }

    private function validateCampaign(Request $request): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'template_name' => ['nullable', 'string', 'max:512'],
            'default_locale' => ['nullable', 'in:en,ar'],
            'scheduled_at' => ['nullable', 'date'],
            'send_rate_per_min' => ['nullable', 'integer', 'min:60', 'max:6000'],
        ]);

        return [
            'name' => $v['name'],
            'template_name' => $v['template_name'] ?? null,
            'default_locale' => $v['default_locale'] ?? 'en',
            'scheduled_at' => $v['scheduled_at'] ?? null,
            'send_rate_per_min' => $v['send_rate_per_min'] ?? 600,
        ];
    }

    /** Conversations (inbox) list. */
    public function conversations(Request $request)
    {
        $this->authorizeAccess($request);

        $page = WaConversation::query()
            ->with('contact')
            ->latest('last_message_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (WaConversation $c) => [
                'id' => $c->id,
                'contact_name' => optional($c->contact)->name,
                'contact_msisdn' => optional($c->contact)->phone,
                'status' => $c->status,
                'last_message_at' => optional($c->last_message_at)->toDateTimeString(),
            ]);

        return Inertia::render('WaModule/Conversations', [
            'page' => $page,
        ]);
    }

    /** Flagship two-pane inbox: conversation list + active thread in one view. */
    public function inbox(Request $request)
    {
        $this->authorizeAccess($request);
        $this->ensureCore(); // make sure the number is wired so chats can be created

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => $request->query('status', 'all'),
        ];

        $conversations = WaConversation::query()
            ->with(['contact', 'lastMessage'])
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', fn ($q) => $q->whereHas('contact', fn ($w) => $w
                ->where('name', 'like', "%{$filters['q']}%")->orWhere('msisdn', 'like', "%{$filters['q']}%")))
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn (WaConversation $c) => [
                'id' => $c->id,
                'name' => optional($c->contact)->name ?: optional($c->contact)->phone ?: '—',
                'msisdn' => optional($c->contact)->phone,
                'status' => $c->status,
                'last_body' => \Illuminate\Support\Str::limit((string) optional($c->lastMessage)->body, 42) ?: null,
                'last_dir' => optional($c->lastMessage)->direction,
                'last_at' => optional($c->last_message_at)->diffForHumans(null, true),
                'last_ts' => optional($c->last_message_at)->timestamp,
            ]);

        $activeId = (int) ($request->query('c') ?: ($conversations[0]['id'] ?? 0));
        $active = null;
        $messages = [];
        if ($activeId) {
            $convo = WaConversation::with('contact')->find($activeId);
            if ($convo) {
                $active = [
                    'id' => $convo->id,
                    'name' => optional($convo->contact)->name ?: optional($convo->contact)->phone ?: '—',
                    'msisdn' => optional($convo->contact)->phone,
                    'status' => $convo->status,
                ];
                $messages = WaMessage::where('conversation_id', $convo->id)
                    ->orderBy('created_at')->limit(300)->get()
                    ->map(fn (WaMessage $m) => [
                        'id' => $m->id,
                        'direction' => $m->direction,
                        'type' => $m->type,
                        'body' => $m->body,
                        'status' => $m->status,
                        'at' => optional($m->created_at)->format('H:i'),
                        'day' => optional($m->created_at)->format('M j'),
                    ])->values();
            }
        }

        $templates = MessageTemplate::query()
            ->where('status', 'APPROVED')
            ->orderBy('name')
            ->get()
            ->map(fn (MessageTemplate $t) => [
                'name' => $t->name,
                'language' => $t->language ?: 'en',
                'body' => $t->body,
                'var_count' => preg_match_all('/\{\{\s*\d+\s*\}\}/', (string) $t->body),
            ])->values();

        return Inertia::render('WaModule/Inbox', [
            'filters' => $filters,
            'conversations' => $conversations,
            'active' => $active,
            'messages' => $messages,
            'templates' => $templates,
            'configured' => (bool) config('services.whatsapp.api_token'),
        ]);
    }

    /** Single conversation thread. */
    public function conversation(Request $request, int $conversation)
    {
        $this->authorizeAccess($request);

        $convo = WaConversation::with('contact')->findOrFail($conversation);

        $messages = WaMessage::where('conversation_id', $convo->id)
            ->orderBy('created_at')
            ->limit(200)
            ->get()
            ->map(fn (WaMessage $m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'type' => $m->type,
                'body' => $m->body,
                'status' => $m->status,
                'created_at' => optional($m->created_at)->toDateTimeString(),
            ]);

        return Inertia::render('WaModule/Conversation', [
            'conversation' => [
                'id' => $convo->id,
                'contact_name' => optional($convo->contact)->name,
                'contact_msisdn' => optional($convo->contact)->phone,
                'status' => $convo->status,
            ],
            'messages' => $messages,
        ]);
    }

    /** Message logs: every WaMessage across conversations, filterable. */
    public function logs(Request $request)
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'direction' => $request->query('direction', 'all'),
            'status' => $request->query('status', 'all'),
        ];

        $page = WaMessage::query()
            ->with('contact')
            ->when($filters['direction'] !== 'all', fn ($q) => $q->where('direction', $filters['direction']))
            ->when($filters['status'] !== 'all', fn ($q) => $q->where('status', $filters['status']))
            ->when($filters['q'] !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('body', 'like', "%{$filters['q']}%")
                ->orWhereHas('contact', fn ($cc) => $cc->where('phone', 'like', "%{$filters['q']}%"))))
            ->latest('id')
            ->paginate(40)
            ->withQueryString()
            ->through(fn (WaMessage $m) => [
                'id' => $m->id,
                'phone' => optional($m->contact)->phone,
                'direction' => $m->direction,
                'type' => $m->type,
                'status' => $m->status,
                'body' => \Illuminate\Support\Str::limit((string) $m->body, 90),
                'full_body' => $m->body,
                'template_name' => $m->template_name,
                'error' => $m->error_message,
                'meta_message_id' => $m->meta_message_id,
                'at' => optional($m->created_at)->toDateTimeString(),
            ]);

        $stats = [
            'total' => WaMessage::count(),
            'in' => WaMessage::where('direction', 'inbound')->count(),
            'out' => WaMessage::where('direction', 'outbound')->count(),
            'failed' => WaMessage::where('status', 'failed')->count(),
        ];

        return Inertia::render('WaModule/Logs', [
            'filters' => $filters,
            'page' => $page,
            'stats' => $stats,
        ]);
    }

    /** Sessions (legacy flow engine state). */
    public function sessions(Request $request)
    {
        $this->authorizeAccess($request);

        $page = WhatsappSession::query()
            ->latest('last_interacted_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (WhatsappSession $s) => [
                'id' => $s->id,
                'phone' => $s->customer_phone_number,
                'name' => $s->customer_name,
                'status' => $s->status,
                'locale' => $s->locale,
                'is_blocked' => (bool) $s->is_blocked,
                'last_interacted_at' => optional($s->last_interacted_at)->toDateTimeString(),
            ]);

        return Inertia::render('WaModule/Sessions', [
            'page' => $page,
        ]);
    }

    public function toggleSessionBlock(Request $request, int $session): RedirectResponse
    {
        $this->authorizeAccess($request);
        $s = WhatsappSession::findOrFail($session);
        $s->update(['is_blocked' => ! $s->is_blocked]);

        return back()->with('flash', ['type' => 'success', 'message' => $s->is_blocked ? 'Session blocked.' : 'Session unblocked.']);
    }

    public function destroySession(Request $request, int $session): RedirectResponse
    {
        $this->authorizeAccess($request);
        WhatsappSession::whereKey($session)->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Session deleted.']);
    }

    /**
     * Ensure a WaAccount + WaCredential + WaNumber exist for the configured
     * number, so the inbox can create conversations and the cloud webhook can
     * route incoming messages. Idempotent. Returns the WaNumber or null.
     */
    private function ensureCore(): ?WaNumber
    {
        $phoneId = config('services.whatsapp.phone_number_id');
        $token = config('services.whatsapp.api_token');
        $waba = config('services.whatsapp.waba_id') ?: config('services.whatsapp.business_account_id');
        if (! $phoneId || ! $token) {
            return null;
        }

        $number = WaNumber::where('phone_number_id', $phoneId)->first();
        if ($number) {
            return $number;
        }

        $account = WaAccount::firstOrCreate(
            ['external_business_id' => $waba],
            ['name' => 'Default WABA', 'status' => 'active']
        );
        $cred = WaCredential::create([
            'wa_account_id' => $account->id,
            'type' => 'system_user',
            'token' => $token,
        ]);

        $health = [];
        try {
            $health = app(WhatsAppService::class)->getCurrentNumberHealth();
        } catch (\Throwable $e) {
        }

        return WaNumber::create([
            'wa_account_id' => $account->id,
            'credential_id' => $cred->id,
            'phone_number_id' => $phoneId,
            'display_phone_number' => $health['display_phone_number'] ?? null,
            'verified_name' => $health['verified_name'] ?? null,
            'waba_id' => $waba,
            'quality_rating' => $health['quality_rating'] ?? null,
            'messaging_limit_tier' => $health['messaging_limit_tier'] ?? null,
            'status' => 'active',
            'account_mode' => 'live',
        ]);
    }

    /** Provision the core records on demand (button on Settings). */
    public function connectNumber(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $number = $this->ensureCore();

        return back()->with('flash', $number
            ? ['type' => 'success', 'message' => 'Number connected to the inbox ('.($number->display_phone_number ?: $number->phone_number_id).').']
            : ['type' => 'error', 'message' => 'WhatsApp credentials missing — cannot connect.']);
    }

    /** Start a new conversation (and optionally send the first message). */
    public function startChat(Request $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:32'],
            'body' => ['nullable', 'string', 'max:4096'],
        ]);

        $number = $this->ensureCore();
        if (! $number) {
            return back()->with('flash', ['type' => 'error', 'message' => 'WhatsApp is not configured.']);
        }

        $e164 = Phone::parseToE164AcrossRegions($data['phone'], ['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'EG'], 'KW')
            ?: preg_replace('/\D+/', '', $data['phone']);

        $digits = preg_replace('/\D+/', '', $e164);
        $contact = WaContact::firstOrCreate(
            ['wa_account_id' => $number->wa_account_id, 'phone' => $e164],
            ['wa_id' => $digits, 'name' => null]
        );
        $convo = WaConversation::findOrCreate($number, $contact);

        if (! empty($data['body'])) {
            $result = $whatsapp->sendTextMessage($e164, $data['body']);
            WaMessage::create([
                'wa_account_id' => $number->wa_account_id,
                'wa_number_id' => $number->id,
                'conversation_id' => $convo->id,
                'contact_id' => $contact->id,
                'direction' => 'outbound',
                'type' => 'text',
                'body' => $data['body'],
                'status' => $result ? 'sent' : 'failed',
                'meta_message_id' => $result['messages'][0]['id'] ?? null,
                'sent_at' => now(),
            ]);
            $convo->update(['last_message_at' => now(), 'last_outgoing_at' => now()]);
        }

        return redirect()->route('v2.wa-module.inbox', ['c' => $convo->id])
            ->with('flash', ['type' => 'success', 'message' => 'Conversation ready.']);
    }

    /** Send an approved template into a conversation (e.g. outside the 24h window). */
    public function sendConversationTemplate(Request $request, int $conversation, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'template' => ['required', 'string'],
            'language' => ['required', 'string'],
            'vars' => ['array'],
            'vars.*' => ['nullable', 'string'],
        ]);
        $convo = WaConversation::with('contact')->findOrFail($conversation);
        $to = optional($convo->contact)->phone;
        if (! $to || ! config('services.whatsapp.api_token')) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Cannot send (no number / not configured).']);
        }

        $components = [];
        $vars = array_values(array_filter($data['vars'] ?? [], fn ($v) => $v !== null && $v !== ''));
        if ($vars) {
            $components[] = ['type' => 'body', 'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => $v], $vars)];
        }
        $payload = ['name' => $data['template'], 'language' => ['code' => $data['language']], 'components' => $components];
        $result = $whatsapp->sendTemplate($to, $payload);

        WaMessage::create([
            'wa_account_id' => $convo->wa_account_id,
            'wa_number_id' => $convo->wa_number_id,
            'conversation_id' => $convo->id,
            'contact_id' => $convo->contact_id,
            'direction' => 'outbound',
            'type' => 'template',
            'body' => '📋 '.$data['template'],
            'status' => $result ? 'sent' : 'failed',
            'meta_message_id' => $result['messages'][0]['id'] ?? null,
            'template_name' => $data['template'],
            'sent_at' => now(),
        ]);
        $convo->update(['last_message_at' => now(), 'last_outgoing_at' => now()]);

        return back()->with('flash', ['type' => $result ? 'success' : 'error', 'message' => $result ? 'Template sent.' : 'Template send failed.']);
    }

    /** Bulk-import campaign recipients from an uploaded CSV/Excel file. */
    public function importRecipients(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
            'has_header' => ['boolean'],
        ]);
        $c = PromotionalCampaign::findOrFail($campaign);

        $path = $request->file('file')->store('wa-imports');
        ImportBulkInviteRecipients::dispatch(
            campaignId: $c->id,
            storedFilePath: $path,
            hasHeader: $request->boolean('has_header', true),
        );

        return back()->with('flash', ['type' => 'success', 'message' => 'Import queued — recipients will appear shortly.']);
    }

    /** Add all contacts from a contact group as campaign recipients (deduped). */
    public function importFromGroup(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate(['group_id' => ['required', 'integer']]);
        $c = PromotionalCampaign::findOrFail($campaign);
        $group = ContactGroup::with('contacts')->findOrFail($data['group_id']);

        $added = 0;
        foreach ($group->contacts as $contact) {
            $rec = PromotionalCampaignRecipient::firstOrCreate(
                ['promotional_campaign_id' => $c->id, 'msisdn' => $contact->msisdn],
                ['name' => $contact->name, 'status' => 'pending', 'locale' => $contact->locale ?: ($c->default_locale ?? 'en'), 'source' => 'group']
            );
            if ($rec->wasRecentlyCreated) {
                $added++;
            }
        }
        $c->update(['total_recipients' => $c->recipients()->count()]);

        return back()->with('flash', ['type' => 'success', 'message' => "Added {$added} recipients from “{$group->name}”."]);
    }

    /** Recompute contact engagement stats from campaign recipient history. */
    public function refreshEngagement(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);

        $agg = PromotionalCampaignRecipient::query()
            ->selectRaw('msisdn,
                COUNT(DISTINCT promotional_campaign_id) campaigns_count,
                SUM(status IN ("sent","delivered","read")) sent_count,
                SUM(status IN ("delivered","read")) delivered_count,
                SUM(status = "read") read_count,
                SUM(status IN ("failed","limited","undeliverable","experiment_blocked")) failed_count,
                SUM(status = "pending") pending_count,
                MAX(GREATEST(COALESCE(read_at,0), COALESCE(delivered_at,0), COALESCE(sent_at,0))) last_activity')
            ->groupBy('msisdn')->get()->keyBy('msisdn');

        $touched = 0;
        Contact::query()->chunkById(500, function ($contacts) use ($agg, &$touched) {
            foreach ($contacts as $contact) {
                $a = $agg->get($contact->msisdn);
                if (! $a) {
                    continue;
                }
                $last = $a->last_activity && $a->last_activity !== '0' ? $a->last_activity : null;
                ContactEngagementStat::updateOrCreate(['contact_id' => $contact->id], [
                    'campaigns_count' => $a->campaigns_count,
                    'sent_count' => $a->sent_count,
                    'delivered_count' => $a->delivered_count,
                    'read_count' => $a->read_count,
                    'failed_count' => $a->failed_count,
                    'pending_count' => $a->pending_count,
                    'last_activity_at' => $last,
                    'is_active' => $last ? \Illuminate\Support\Carbon::parse($last)->gt(now()->subDays(30)) : false,
                ]);
                $touched++;
            }
        });

        return back()->with('flash', ['type' => 'success', 'message' => "Engagement refreshed for {$touched} contacts."]);
    }

    /** Create a dynamic group and populate it from engagement filters. */
    public function buildSmartGroup(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'filter' => ['required', 'in:active,healthy,delivered,read'],
        ]);

        $group = ContactGroup::create([
            'name' => $data['name'],
            'group_type' => 'dynamic',
            'filters_json' => ['engagement' => $data['filter']],
            'last_synced_at' => now(),
        ]);

        $q = Contact::query()->whereHas('engagementStat', function ($s) use ($data) {
            match ($data['filter']) {
                'active' => $s->where('is_active', true),
                'healthy' => $s->where('delivered_count', '>', 0)->where('failed_count', '<=', 1),
                'delivered' => $s->where('delivered_count', '>', 0),
                'read' => $s->where('read_count', '>', 0),
            };
        });
        $ids = $q->pluck('id');
        $group->contacts()->sync($ids);

        return back()->with('flash', ['type' => 'success', 'message' => "Smart group “{$group->name}” created with {$ids->count()} contacts."]);
    }

    /** Send a free-text reply within a conversation thread. */
    public function replyConversation(Request $request, int $conversation, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate(['body' => ['required', 'string', 'max:4096']]);
        $convo = WaConversation::with('contact')->findOrFail($conversation);
        $to = optional($convo->contact)->phone;

        if (! $to) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Conversation has no contact number.']);
        }
        if (! config('services.whatsapp.api_token')) {
            return back()->with('flash', ['type' => 'error', 'message' => 'WhatsApp is not configured.']);
        }

        $result = $whatsapp->sendTextMessage($to, $data['body']);

        // Persist the outgoing message into the thread regardless, marking status.
        WaMessage::create([
            'wa_account_id' => $convo->wa_account_id,
            'wa_number_id' => $convo->wa_number_id,
            'conversation_id' => $convo->id,
            'contact_id' => $convo->contact_id,
            'direction' => 'outbound',
            'type' => 'text',
            'body' => $data['body'],
            'status' => $result ? 'sent' : 'failed',
            'meta_message_id' => $result['messages'][0]['id'] ?? null,
            'sent_at' => now(),
        ]);
        $convo->update(['last_message_at' => now(), 'last_outgoing_at' => now()]);

        return back()->with('flash', [
            'type' => $result ? 'success' : 'error',
            'message' => $result ? 'Reply sent.' : 'Send failed — saved as failed.',
        ]);
    }

    /** Setting keys the bot reads (Wave\Setting-backed). */
    private const SETTING_KEYS = [
        'whatsapp.entry_mode',
        'whatsapp.banner_greeting_en', 'whatsapp.banner_greeting_ar',
        'whatsapp.flow_welcome_en', 'whatsapp.flow_welcome_ar',
        'whatsapp.fallback_reply_en', 'whatsapp.fallback_reply_ar',
        'whatsapp.about_reply_en', 'whatsapp.about_reply_ar',
        'whatsapp.pricing_reply_en', 'whatsapp.pricing_reply_ar',
        'whatsapp.privacy_reply_en', 'whatsapp.privacy_reply_ar',
        'whatsapp.frequency_cap_whitelist',
        'whatsapp.stop_keywords',
        'wa_initiation_restricted',
    ];

    public function settings(Request $request, WhatsAppService $whatsapp)
    {
        $this->authorizeAccess($request);

        $stored = \Wave\Setting::whereIn('key', self::SETTING_KEYS)->pluck('value', 'key')->toArray();
        $settings = [];
        foreach (self::SETTING_KEYS as $k) {
            $settings[$k] = $stored[$k] ?? '';
        }

        $health = ['status' => 'unknown'];
        if (config('services.whatsapp.api_token')) {
            try {
                $health = $whatsapp->getCurrentNumberHealth();
            } catch (\Throwable $e) {
                $health = ['status' => 'error', 'message' => $e->getMessage()];
            }
        } else {
            $health = ['status' => 'missing_credentials'];
        }

        return Inertia::render('WaModule/Settings', [
            'settings' => $settings,
            'health' => $health,
            'configured' => (bool) config('services.whatsapp.api_token'),
        ]);
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $payload = (array) $request->input('settings', []);
        foreach (self::SETTING_KEYS as $k) {
            if (! array_key_exists($k, $payload)) {
                continue;
            }
            \Wave\Setting::updateOrCreate(['key' => $k], ['value' => (string) $payload[$k]]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Settings saved.']);
    }

    /** Send a free-text message to a number via the module's WhatsApp client. */
    public function sendMessage(Request $request, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);

        $data = $request->validate([
            'to' => ['required', 'string', 'max:20'],
            'body' => ['required', 'string', 'max:4096'],
        ]);

        if (! config('services.whatsapp.api_token')) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'WhatsApp is not configured (missing WHATSAPP_API_TOKEN).',
            ]);
        }

        $result = $whatsapp->sendTextMessage($data['to'], $data['body']);

        return back()->with('flash', [
            'type' => $result ? 'success' : 'error',
            'message' => $result ? 'Message sent.' : 'Send failed — check logs / credentials.',
        ]);
    }
}
