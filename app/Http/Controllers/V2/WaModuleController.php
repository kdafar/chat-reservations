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
        $data = $this->validateTemplate($request, null, $whatsapp);

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
    public function updateTemplate(Request $request, int $template, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);
        $tpl = MessageTemplate::findOrFail($template);

        if ($tpl->status === 'APPROVED' || $tpl->local_status === 'published') {
            return back()->with('flash', ['type' => 'error', 'message' => 'Approved/published templates cannot be edited.']);
        }

        $tpl->update($this->validateTemplate($request, $tpl, $whatsapp));

        return back()->with('flash', ['type' => 'success', 'message' => 'Template updated.']);
    }

    /** Refresh a template's Meta status (APPROVED/PENDING/REJECTED + components). */
    public function refreshTemplateStatus(Request $request, int $template, WhatsAppService $whatsapp): RedirectResponse
    {
        $this->authorizeAccess($request);
        $tpl = MessageTemplate::findOrFail($template);
        if (blank($tpl->meta_id)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Template has not been submitted to Meta yet.']);
        }
        try {
            $whatsapp->refreshTemplateStatus($tpl);
            return back()->with('flash', ['type' => 'success', 'message' => 'Status refreshed: '.($tpl->fresh()->status ?: 'unknown').'.']);
        } catch (\Throwable $e) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Refresh failed: '.$e->getMessage()]);
        }
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

    /** Normalize a template name to Meta rules + enforce the _en/_ar suffix. */
    private function normalizeTemplateName(string $input, string $lang): string
    {
        $lang = $lang === 'ar' ? 'ar' : 'en';
        $name = strtolower(trim($input));
        $name = preg_replace('/\s+/', '_', $name);
        $name = preg_replace('/[^a-z0-9_]/', '', $name);
        $name = preg_replace('/_+/', '_', $name);
        $name = trim($name, '_');
        $name = preg_replace('/_(en|ar)$/', '', $name);

        return $name === '' ? '' : $name.'_'.$lang;
    }

    /**
     * Validate + compose a template, enforcing the same Meta guardrails the
     * source Filament wizard enforces (server-side = authoritative).
     */
    private function validateTemplate(Request $request, ?MessageTemplate $record = null, WhatsAppService $whatsapp = null): array
    {
        $whatsapp ??= app(WhatsAppService::class);
        $locked = $record && ($record->local_status === 'published' || $record->status === 'APPROVED');

        $v = $request->validate([
            'name' => ['required', 'string', 'max:512'],
            'category' => ['required', 'in:MARKETING,UTILITY,AUTHENTICATION'],
            'language' => ['required', 'in:en,ar'],
            'header_type' => ['nullable', 'in:NONE,TEXT,IMAGE,VIDEO,DOCUMENT'],
            'header_text' => ['nullable', 'string', 'max:60'],
            'header_example' => ['nullable', 'string', 'max:120'],
            'header_media_url' => ['nullable', 'url', 'max:2048'],
            'header_media_type' => ['nullable', 'string', 'max:120'], // mime of the sample, for type match
            'body' => ['required', 'string', 'max:1024'],
            'body_examples' => ['array'],
            'body_examples.*' => ['nullable', 'string', 'max:200'],
            'footer_text' => ['nullable', 'string', 'max:60'],
            'is_auto_reply' => ['boolean'],
            'triggers' => ['array'],
            'triggers.*' => ['string', 'max:60'],
            'buttons' => ['array', 'max:3'],
            'buttons.*.type' => ['required_with:buttons', 'in:QUICK_REPLY,URL,PHONE_NUMBER'],
            'buttons.*.text' => ['required_with:buttons', 'string', 'max:25'],
            'buttons.*.url' => ['nullable', 'string', 'max:2048'],
            'buttons.*.phone_number' => ['nullable', 'string', 'max:20'],
        ]);

        $name = $this->normalizeTemplateName($v['name'], $v['language']);
        $body = $v['body'];
        $headerType = $v['header_type'] ?? 'NONE';
        $errors = [];

        // --- name rules ---
        if (! preg_match('/^[a-z0-9_]+$/', $name)) {
            $errors['name'] = 'Meta requires strictly lowercase letters, numbers, and underscores.';
        } elseif (! $locked && ! preg_match('/_(en|ar)$/', $name)) {
            $errors['name'] = 'Name must end with _en or _ar.';
        } elseif (MessageTemplate::where('name', $name)->when($record, fn ($q) => $q->whereKeyNot($record->id))->exists()) {
            $errors['name'] = "A template named '{$name}' already exists locally.";
        } elseif ((! $record || $record->name !== $name) && ! $locked && $whatsapp->doesTemplateExist($name)) {
            $errors['name'] = "A template '{$name}' already exists on your Meta Business Account.";
        }

        // --- body rules (start/end var, whitespace, sequential 1..10) ---
        if (preg_match('/^\s*\{\{\s*\d+\s*\}\}/', $body)) {
            $errors['body'] = 'Meta rejects templates starting with a variable. Add text before {{1}}.';
        } elseif (preg_match('/\{\{\s*\d+\s*\}\}\s*$/', $body)) {
            $errors['body'] = 'Meta rejects templates ending with a variable. Add text after the last variable.';
        } elseif (str_contains($body, "\t")) {
            $errors['body'] = 'Tabs are not allowed.';
        } elseif (preg_match('/ {4,}/', $body)) {
            $errors['body'] = 'Too many consecutive spaces (max 4).';
        } elseif (preg_match('/[\r\n]{3,}/', $body)) {
            $errors['body'] = 'Too many consecutive newlines (max 2).';
        }
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $m);
        $nums = collect($m[1] ?? [])->map(fn ($n) => (int) $n)->unique()->sort()->values()->all();
        if (! isset($errors['body'])) {
            if (count($nums) > 10) {
                $errors['body'] = 'Meta allows a maximum of 10 body variables.';
            } elseif (! empty($nums)) {
                foreach ($nums as $i => $n) {
                    if ($n !== $i + 1) {
                        $errors['body'] = 'Variables must be sequential starting at {{1}} (missing {{'.($i + 1).'}}).';
                        break;
                    }
                }
            }
        }
        // body variable samples required (one per distinct variable)
        $examples = array_values(array_filter($v['body_examples'] ?? [], fn ($x) => $x !== null && $x !== ''));
        if (! empty($nums) && count($examples) < count($nums)) {
            $errors['body_examples'] = 'Provide a sample value for each body variable (for Meta approval).';
        }

        // --- header rules ---
        if ($headerType === 'TEXT') {
            $headerText = (string) ($v['header_text'] ?? '');
            preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $headerText, $hm);
            $headerVars = collect($hm[1] ?? [])->map(fn ($n) => (int) $n)->unique()->values()->all();
            if (blank($v['header_text'] ?? null)) {
                $errors['header_text'] = 'Header text is required for a TEXT header.';
            } elseif (count($headerVars) > 1 || (count($headerVars) === 1 && $headerVars[0] !== 1)) {
                // Meta allows at most ONE header variable and it must be {{1}}.
                $errors['header_text'] = 'A TEXT header may contain only a single variable, {{1}}.';
            } elseif (count($headerVars) === 1 && blank($v['header_example'] ?? null)) {
                $errors['header_example'] = 'A sample is required for the header variable.';
            }
        } elseif (in_array($headerType, ['IMAGE', 'VIDEO', 'DOCUMENT'], true)) {
            if (blank($v['header_media_url'] ?? null)) {
                $errors['header_media_url'] = "A sample {$headerType} is required for approval.";
            } elseif (! empty($v['header_media_type'])) {
                $type = strtolower($v['header_media_type']);
                $ok = match ($headerType) {
                    'IMAGE' => str_starts_with($type, 'image/'),
                    'VIDEO' => str_starts_with($type, 'video/'),
                    'DOCUMENT' => $type === 'application/pdf',
                    default => true,
                };
                if (! $ok) {
                    $errors['header_media_url'] = "Sample media does not match header type ({$headerType}).";
                }
            }
        }

        // --- footer: no variables ---
        if (! empty($v['footer_text']) && preg_match('/\{\{.*\}\}/', $v['footer_text'])) {
            $errors['footer_text'] = 'Footer cannot contain variables.';
        }

        // --- button mix rules ---
        $btnRows = $v['buttons'] ?? [];
        $types = collect($btnRows)->pluck('type')->filter();
        if ($types->contains('QUICK_REPLY') && ($types->contains('URL') || $types->contains('PHONE_NUMBER'))) {
            $errors['buttons'] = 'Cannot mix Quick Reply buttons with Link/Phone buttons.';
        } elseif ($types->filter(fn ($t) => $t === 'URL')->count() > 1) {
            $errors['buttons'] = 'Only one Website Link button is allowed.';
        } elseif ($types->filter(fn ($t) => $t === 'PHONE_NUMBER')->count() > 1) {
            $errors['buttons'] = 'Only one Phone Number button is allowed.';
        } else {
            foreach ($btnRows as $b) {
                if (($b['type'] ?? '') === 'URL' && ! preg_match('#^https://#', (string) ($b['url'] ?? ''))) {
                    $errors['buttons'] = 'URL buttons must use an https:// link.';
                }
                if (($b['type'] ?? '') === 'PHONE_NUMBER' && ! preg_match('/^[0-9]{5,20}$/', (string) ($b['phone_number'] ?? ''))) {
                    $errors['buttons'] = 'Phone buttons need digits only (5–20, no +).';
                }
            }
        }

        if ($errors) {
            throw \Illuminate\Validation\ValidationException::withMessages($errors);
        }

        $v['name'] = $name;
        $v['body_examples'] = $examples;

        // header_type/header_text/footer/buttons are not columns — they live
        // inside the Meta `components` structure. Build that here.
        $components = [];
        if ($headerType !== 'NONE') {
            $header = ['type' => 'HEADER', 'format' => $headerType];
            if ($headerType === 'TEXT') {
                $header['text'] = $v['header_text'] ?? null;
                if (! empty($v['header_example'])) {
                    $header['example'] = ['header_text' => [$v['header_example']]];
                }
            } elseif (! empty($v['header_media_url'])) {
                $header['example'] = ['header_handle' => [$v['header_media_url']]];
                $header['media_url'] = $v['header_media_url'];
            }
            $components[] = array_filter($header);
        }
        $bodyComp = ['type' => 'BODY', 'text' => $body];
        if (! empty($examples)) {
            $bodyComp['example'] = ['body_text' => [array_values($examples)]];
        }
        $components[] = $bodyComp;
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
            'name' => $name,
            'category' => $v['category'],
            'language' => $v['language'],
            'body' => $body,
            'body_preview' => \Illuminate\Support\Str::limit($body, 160),
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
        $body = $byType('BODY');
        $footer = $byType('FOOTER');
        $buttonsC = $byType('BUTTONS');

        return [
            'header_type' => $header['format'] ?? 'NONE',
            'header_text' => $header['text'] ?? null,
            'header_example' => $header['example']['header_text'][0] ?? null,
            'header_media_url' => $header['media_url'] ?? ($header['example']['header_handle'][0] ?? null),
            'footer_text' => $footer['text'] ?? null,
            'body_examples' => $body['example']['body_text'][0] ?? [],
            'locked' => $t->local_status === 'published' || $t->status === 'APPROVED',
            'has_meta_id' => filled($t->meta_id),
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
                    'replied' => $c->engagementStat->replied_count,
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
                'locked' => $this->campaignLocked($c),
                'recipients_count' => $c->recipients_count,
                'pending_count' => $c->pending_count,
                'sent_count' => $c->sent_count,
                'default_locale' => $c->default_locale,
                'send_rate_per_min' => $c->send_rate_per_min,
                'template_variables' => (array) $c->template_variables,
                'has_header_media' => filled($c->header_image_path),
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
                'components' => $t->components ?? [],
                'var_indexes' => $this->templateBodyVarIndexes($t->components ?? []),
                'needs_media' => in_array(strtoupper((string) data_get(collect($t->components ?? [])->firstWhere('type', 'HEADER'), 'format')), ['IMAGE', 'VIDEO', 'DOCUMENT'], true),
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

    /** True once a campaign is locked (sending/completed/paused, sent, or has recipients). */
    private function campaignLocked(PromotionalCampaign $c): bool
    {
        return in_array($c->status, ['sending', 'completed', 'paused'], true)
            || filled($c->sent_at)
            || $c->recipients()->exists();
    }

    /** Body variable indexes ({{n}}) for the selected template, from its components. */
    private function templateBodyVarIndexes(?array $components): array
    {
        $body = collect($components ?? [])->firstWhere('type', 'BODY');
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', (string) ($body['text'] ?? ''), $m);

        return collect($m[1] ?? [])->map(fn ($i) => (int) $i)->unique()->sort()->values()->all();
    }

    public function storeCampaign(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $this->validateCampaign($request);
        $status = ! empty($data['scheduled_at']) && \Illuminate\Support\Carbon::parse($data['scheduled_at'])->isFuture() ? 'scheduled' : 'draft';
        PromotionalCampaign::create($data + ['status' => $status]);

        return back()->with('flash', ['type' => 'success', 'message' => $status === 'scheduled' ? 'Campaign scheduled.' : 'Campaign created.']);
    }

    public function updateCampaign(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $c = PromotionalCampaign::findOrFail($campaign);
        if ($this->campaignLocked($c)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'This campaign is locked (sending/sent or has recipients) and cannot be edited.']);
        }
        $data = $this->validateCampaign($request, $c);
        if (in_array($c->status, ['draft', 'scheduled'], true)) {
            $data['status'] = ! empty($data['scheduled_at']) && \Illuminate\Support\Carbon::parse($data['scheduled_at'])->isFuture() ? 'scheduled' : 'draft';
        }
        $c->update($data);

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

    /**
     * Shared sendability validation (template, header media, body variables).
     * Returns an error string, or null if the campaign can be sent/tested.
     */
    private function campaignNotSendableReason(PromotionalCampaign $c): ?string
    {
        if (! config('services.whatsapp.api_token')) {
            return 'WhatsApp is not configured.';
        }
        if (! $c->template_name || ! $c->template_details) {
            return 'A valid template must be selected.';
        }
        $components = (array) data_get($c->template_details, 'components', []);
        $header = collect($components)->firstWhere('type', 'HEADER');
        if ($header && in_array(strtoupper((string) ($header['format'] ?? '')), ['IMAGE', 'VIDEO', 'DOCUMENT'], true) && blank($c->header_image_path)) {
            return 'This template needs a header file, but none is set.';
        }
        foreach ($this->templateBodyVarIndexes($components) as $i) {
            if (blank(data_get($c->template_variables, (string) $i))) {
                return "Template variable {{{$i}}} is required but empty.";
            }
        }

        return null;
    }

    /**
     * Validate the campaign is sendable, then queue all pending+failed
     * recipients (resetting failed -> pending). Ports the source "Validate &
     * Queue" action.
     */
    public function sendCampaign(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $c = PromotionalCampaign::findOrFail($campaign);
        $err = fn ($msg) => back()->with('flash', ['type' => 'error', 'message' => $msg]);

        if ($c->status === 'completed') {
            return $err('Campaign is already completed.');
        }
        if ($reason = $this->campaignNotSendableReason($c)) {
            return $err($reason);
        }
        $ids = $c->recipients()->whereIn('status', ['pending', 'failed'])->pluck('id');
        if ($ids->isEmpty()) {
            return $err('No pending or failed recipients to queue.');
        }

        $c->update(['status' => 'sending', 'sent_at' => $c->sent_at ?? now()]);
        $c->recipients()->where('status', 'failed')->update(['status' => 'pending', 'error_message' => null]);
        foreach ($ids as $rid) {
            SendPromotionalCampaignMessage::dispatch($c->id, $rid);
        }

        return back()->with('flash', ['type' => 'success', 'message' => "Queued {$ids->count()} messages."]);
    }

    /** Queue a single test message to one number (bypasses frequency cap). */
    public function testCampaign(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'test_msisdn' => ['required', 'string', 'max:32'],
            'preferred_region' => ['nullable', 'in:KW,SA,AE,QA,BH,OM,EG'],
        ]);
        $c = PromotionalCampaign::findOrFail($campaign);
        // Test send runs the SAME sendability checks as a real send.
        if ($reason = $this->campaignNotSendableReason($c)) {
            return back()->with('flash', ['type' => 'error', 'message' => $reason]);
        }
        $e164 = Phone::parseToE164AcrossRegions($data['test_msisdn'], ['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'EG'], $data['preferred_region'] ?? 'KW', true);
        if (! $e164) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Invalid mobile number (GCC + Egypt).']);
        }
        $rec = PromotionalCampaignRecipient::firstOrNew(['promotional_campaign_id' => $c->id, 'msisdn' => $e164]);
        $rec->fill(['status' => 'pending', 'error_message' => null, 'wa_message_id' => null, 'source' => 'test', 'locale' => $c->default_locale ?? 'en'])->save();
        SendPromotionalCampaignMessage::dispatch($c->id, $rec->id, true);

        return back()->with('flash', ['type' => 'success', 'message' => "Test queued to {$e164}."]);
    }

    public function pauseCampaign(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $c = PromotionalCampaign::findOrFail($campaign);
        if ($c->status !== 'sending') {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only a sending campaign can be paused.']);
        }
        $c->update(['status' => 'paused']);

        return back()->with('flash', ['type' => 'success', 'message' => 'Campaign paused.']);
    }

    public function resumeCampaign(Request $request, int $campaign): RedirectResponse
    {
        $this->authorizeAccess($request);
        $c = PromotionalCampaign::findOrFail($campaign);
        if ($c->status !== 'paused') {
            return back()->with('flash', ['type' => 'error', 'message' => 'Only a paused campaign can be resumed.']);
        }
        // Re-queue still-pending recipients so they continue.
        $c->update(['status' => 'sending']);
        foreach ($c->recipients()->where('status', 'pending')->pluck('id') as $rid) {
            SendPromotionalCampaignMessage::dispatch($c->id, $rid);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Campaign resumed.']);
    }

    /** Retry a single failed recipient. */
    public function retryRecipient(Request $request, int $campaign, int $recipient): RedirectResponse
    {
        $this->authorizeAccess($request);
        $c = PromotionalCampaign::findOrFail($campaign);
        $rec = $c->recipients()->whereKey($recipient)->firstOrFail();
        $rec->update(['status' => 'pending', 'error_message' => null, 'wa_message_id' => null]);
        SendPromotionalCampaignMessage::dispatch($c->id, $rec->id, true);

        return back()->with('flash', ['type' => 'success', 'message' => 'Recipient re-queued.']);
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

    private function validateCampaign(Request $request, ?PromotionalCampaign $existing = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'template_name' => ['nullable', 'string', 'max:512'],
            'default_locale' => ['nullable', 'in:en,ar'],
            'scheduled_at' => ['nullable', 'date'],
            'send_rate_per_min' => ['nullable', 'integer', 'min:60', 'max:6000'],
            'template_variables' => ['array'],
            'template_variables.*' => ['nullable', 'string', 'max:1024'],
            'header_media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,pdf', 'max:16384'],
        ]);

        $out = [
            'name' => $v['name'],
            'template_name' => $v['template_name'] ?? null,
            'scheduled_at' => $v['scheduled_at'] ?? null,
            'send_rate_per_min' => $v['send_rate_per_min'] ?? 600,
        ];

        // Hydrate template_details + locale + variable values from the local template.
        $tpl = ! empty($v['template_name']) ? MessageTemplate::where('name', $v['template_name'])->first() : null;
        if ($tpl) {
            $out['template_details'] = ['name' => $tpl->name, 'language' => $tpl->language, 'components' => $tpl->components ?? []];
            $out['default_locale'] = $v['default_locale'] ?? ($tpl->language ?: 'en');
            // keep only the variables this template actually has, index-keyed
            $indexes = $this->templateBodyVarIndexes($tpl->components ?? []);
            $vars = [];
            foreach ($indexes as $i) {
                $vars[(string) $i] = (string) data_get($v, "template_variables.$i", '');
            }
            $out['template_variables'] = $vars;
        } else {
            $out['default_locale'] = $v['default_locale'] ?? 'en';
            $out['template_details'] = null;
            $out['template_variables'] = [];
        }

        // Header media: store an uploaded sample to the public disk (the send job
        // resolves it via Storage::disk('public')->url($header_image_path)).
        if ($request->hasFile('header_media')) {
            $out['header_image_path'] = $request->file('header_media')->store('wa-campaign-media', 'public');
        } elseif ($existing) {
            $out['header_image_path'] = $existing->header_image_path; // keep existing on edit
        }

        return $out;
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
                ->where('name', 'like', "%{$filters['q']}%")->orWhere('phone', 'like', "%{$filters['q']}%")))
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
                // 24-hour customer service window: free text only sends within
                // 24h of the contact's LAST inbound message; otherwise template-only.
                $lastInbound = WaMessage::where('conversation_id', $convo->id)
                    ->where('direction', 'inbound')->max('created_at');
                $windowOpen = $lastInbound && \Illuminate\Support\Carbon::parse($lastInbound)->gt(now()->subDay());

                $active = [
                    'id' => $convo->id,
                    'name' => optional($convo->contact)->name ?: optional($convo->contact)->phone ?: '—',
                    'msisdn' => optional($convo->contact)->phone,
                    'status' => $convo->status,
                    'window_open' => $windowOpen,
                    'window_expires' => $windowOpen ? \Illuminate\Support\Carbon::parse($lastInbound)->addDay()->diffForHumans() : null,
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

    /** Build a base Contact query from audience filter params (engagement joined). */
    private function audienceQuery(Request $request)
    {
        $q = Contact::query()
            ->leftJoin('contact_engagement_stats as es', 'contacts.id', '=', 'es.contact_id')
            ->select('contacts.*');

        if ($s = trim((string) $request->query('q', ''))) {
            $q->where(fn ($w) => $w->where('contacts.msisdn', 'like', "%{$s}%")->orWhere('contacts.name', 'like', "%{$s}%"));
        }
        if ($loc = $request->query('locale')) {
            $q->where('contacts.locale', $loc);
        }
        if ($request->boolean('active')) {
            $q->where('es.is_active', true);
        }
        if ($request->boolean('delivered')) {
            $q->where('es.delivered_count', '>', 0);
        }
        if ($request->boolean('read')) {
            $q->where('es.read_count', '>', 0);
        }
        if ($request->boolean('replied')) {
            $q->where('es.replied_count', '>', 0);
        }
        if ($request->boolean('healthy')) {
            $q->where('es.delivered_count', '>', 0)->where('es.failed_count', '<=', 1);
        }
        if (($mf = $request->query('max_failed')) !== null && $mf !== '') {
            $q->where('es.failed_count', '<=', (int) $mf);
        }
        if (($d = $request->query('days')) !== null && $d !== '') {
            $q->where('es.last_activity_at', '>=', now()->subDays((int) $d));
        }
        if ($gid = $request->query('in_group')) {
            $q->whereHas('groups', fn ($g) => $g->whereKey($gid));
        }
        if ($gid = $request->query('not_in_group')) {
            $q->whereDoesntHave('groups', fn ($g) => $g->whereKey($gid));
        }

        return $q;
    }

    /** Audience builder: filterable + sortable contacts table with engagement. */
    public function audience(Request $request)
    {
        $this->authorizeAccess($request);

        $sortable = ['msisdn' => 'contacts.msisdn', 'name' => 'contacts.name', 'sent' => 'es.sent_count', 'delivered' => 'es.delivered_count', 'read' => 'es.read_count', 'failed' => 'es.failed_count', 'replied' => 'es.replied_count', 'last' => 'es.last_activity_at'];
        $sort = $request->query('sort', 'last');
        $col = $sortable[$sort] ?? 'es.last_activity_at';
        $dir = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $matched = (clone $this->audienceQuery($request))->count();

        $page = $this->audienceQuery($request)
            ->with('engagementStat', 'groups:id')
            ->orderBy($col, $dir)
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Contact $c) => [
                'id' => $c->id,
                'msisdn' => $c->msisdn,
                'name' => $c->name,
                'locale' => $c->locale,
                'group_ids' => $c->groups->pluck('id'),
                'sent' => $c->engagementStat->sent_count ?? 0,
                'delivered' => $c->engagementStat->delivered_count ?? 0,
                'read' => $c->engagementStat->read_count ?? 0,
                'failed' => $c->engagementStat->failed_count ?? 0,
                'replied' => $c->engagementStat->replied_count ?? 0,
                'active' => (bool) ($c->engagementStat->is_active ?? false),
                'last' => optional($c->engagementStat?->last_activity_at)->diffForHumans(null, true),
            ]);

        $filters = $request->only(['q', 'locale', 'active', 'delivered', 'read', 'replied', 'healthy', 'max_failed', 'days', 'in_group', 'not_in_group', 'sort', 'dir']);

        return Inertia::render('WaModule/Audience', [
            'filters' => $filters,
            'page' => $page,
            'matched' => $matched,
            'groups' => ContactGroup::orderBy('name')->withCount('contacts')->get()->map(fn ($g) => ['id' => $g->id, 'name' => $g->name, 'count' => $g->contacts_count]),
        ]);
    }

    /** Add selected (or all matching) contacts to a group; add or replace. */
    public function audienceToGroup(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'group_id' => ['nullable', 'integer'],
            'new_group' => ['nullable', 'string', 'max:191'],
            'mode' => ['required', 'in:add,replace'],
            'all_matching' => ['boolean'],
            'ids' => ['array'],
            'ids.*' => ['integer'],
        ]);

        $group = ! empty($data['group_id'])
            ? ContactGroup::findOrFail($data['group_id'])
            : ContactGroup::create(['name' => ($data['new_group'] ?? null) ?: 'New group', 'group_type' => 'static']);

        $ids = $request->boolean('all_matching')
            ? $this->audienceQuery($request)->pluck('contacts.id')->all()
            : ($data['ids'] ?? []);

        if (empty($ids)) {
            return back()->with('flash', ['type' => 'error', 'message' => 'No contacts selected.']);
        }

        $data['mode'] === 'replace' ? $group->contacts()->sync($ids) : $group->contacts()->syncWithoutDetaching($ids);

        return back()->with('flash', ['type' => 'success', 'message' => count($ids).' contacts '.($data['mode'] === 'replace' ? 'set as' : 'added to')." “{$group->name}”."]);
    }

    /** Export contacts (respects audience filters) as CSV. */
    public function exportContacts(Request $request)
    {
        $this->authorizeAccess($request);
        $rows = $this->audienceQuery($request)->with('engagementStat')->limit(50000)->get();

        $out = "phone,name,locale,sent,delivered,read,failed,replied,active\n";
        foreach ($rows as $c) {
            $e = $c->engagementStat;
            $out .= implode(',', [
                $c->msisdn, '"'.str_replace('"', '""', (string) $c->name).'"', $c->locale,
                $e->sent_count ?? 0, $e->delivered_count ?? 0, $e->read_count ?? 0, $e->failed_count ?? 0, $e->replied_count ?? 0, ($e->is_active ?? false) ? 1 : 0,
            ])."\n";
        }

        return response($out, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="wa-contacts.csv"',
        ]);
    }

    /** Import contacts into the directory (optionally into a group) from CSV. */
    public function importContacts(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'group_id' => ['nullable', 'integer'],
            'has_header' => ['boolean'],
        ]);

        $lines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($request->file('file')->getRealPath()));
        if ($request->boolean('has_header', true)) {
            array_shift($lines);
        }
        $ids = [];
        $created = 0;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$phone, $name, $locale] = array_pad(str_getcsv($line), 3, null);
            $phone = preg_replace('/[^\d+]/', '', (string) $phone);
            if (! $phone) {
                continue;
            }
            $contact = Contact::firstOrCreate(['msisdn' => $phone], ['name' => $name ?: null, 'locale' => in_array($locale, ['en', 'ar']) ? $locale : 'en']);
            $created += $contact->wasRecentlyCreated ? 1 : 0;
            $ids[] = $contact->id;
        }
        if (! empty($data['group_id']) && $ids) {
            ContactGroup::find($data['group_id'])?->contacts()->syncWithoutDetaching($ids);
        }

        return back()->with('flash', ['type' => 'success', 'message' => count($ids).' contacts imported ('.$created.' new).']);
    }

    /** Recompute contact engagement stats (delegates to the shared command logic). */
    public function refreshEngagement(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $touched = \App\Wa\Console\Commands\RefreshContactEngagementStats::rebuild();

        return back()->with('flash', ['type' => 'success', 'message' => "Engagement refreshed for {$touched} contacts."]);
    }

    /** @deprecated kept for reference; logic moved to RefreshContactEngagementStats::rebuild(). */
    private function refreshEngagementInline(Request $request): RedirectResponse
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

        // replied_count: inbound messages per phone (joined via the core contact).
        // selectRaw bypasses the connection prefix, so qualify columns explicitly.
        $p = (new WaMessage)->getConnection()->getTablePrefix();
        $replied = WaMessage::query()
            ->where('wa_messages.direction', 'inbound')
            ->join('wa_contacts', 'wa_messages.contact_id', '=', 'wa_contacts.id')
            ->selectRaw("{$p}wa_contacts.phone as phone, COUNT(*) replied, MAX({$p}wa_messages.created_at) last_replied")
            ->groupBy('wa_contacts.phone')->get()->keyBy('phone');

        $touched = 0;
        Contact::query()->chunkById(500, function ($contacts) use ($agg, $replied, &$touched) {
            foreach ($contacts as $contact) {
                $a = $agg->get($contact->msisdn);
                $rep = $replied->get($contact->msisdn) ?? $replied->get(ltrim((string) $contact->msisdn, '+')) ?? $replied->get('+'.ltrim((string) $contact->msisdn, '+'));
                if (! $a && ! $rep) {
                    continue;
                }
                $last = $a && $a->last_activity && $a->last_activity !== '0' ? $a->last_activity : null;
                $lastReplied = $rep->last_replied ?? null;
                $lastAct = collect([$last, $lastReplied])->filter()->max();
                ContactEngagementStat::updateOrCreate(['contact_id' => $contact->id], [
                    'campaigns_count' => $a->campaigns_count ?? 0,
                    'sent_count' => $a->sent_count ?? 0,
                    'delivered_count' => $a->delivered_count ?? 0,
                    'read_count' => $a->read_count ?? 0,
                    'failed_count' => $a->failed_count ?? 0,
                    'pending_count' => $a->pending_count ?? 0,
                    'replied_count' => $rep->replied ?? 0,
                    'last_replied_at' => $lastReplied,
                    'last_activity_at' => $lastAct ?: null,
                    'is_active' => $lastAct ? \Illuminate\Support\Carbon::parse($lastAct)->gt(now()->subDays(30)) : false,
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
        // Enforce the 24-hour window: free text is only allowed within 24h of the
        // contact's last inbound message — otherwise an approved template is required.
        $lastInbound = WaMessage::where('conversation_id', $convo->id)->where('direction', 'inbound')->max('created_at');
        if (! ($lastInbound && \Illuminate\Support\Carbon::parse($lastInbound)->gt(now()->subDay()))) {
            return back()->with('flash', ['type' => 'error', 'message' => 'Outside the 24-hour window — send an approved template instead.']);
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
