<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Wa\Hub\Models\Contact;
use App\Wa\Hub\Models\ContactGroup;
use App\Wa\Hub\Models\MessageTemplate;
use App\Wa\Hub\Models\PromotionalCampaign;
use App\Wa\Hub\Models\WhatsappSession;
use App\Wa\Models\WhatsApp\WaConversation;
use App\Wa\Models\WhatsApp\WaMessage;
use App\Wa\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'body_preview' => \Illuminate\Support\Str::limit((string) ($t->body_preview ?: $t->body), 120),
                'updated_at' => optional($t->updated_at)->toDateTimeString(),
            ]);

        return Inertia::render('WaModule/Templates', [
            'filters' => $filters,
            'page' => $page,
        ]);
    }

    /** Contacts + groups. */
    public function contacts(Request $request)
    {
        $this->authorizeAccess($request);

        $filters = ['q' => trim((string) $request->query('q', ''))];

        $page = Contact::query()
            ->when($filters['q'] !== '', fn ($q) => $q
                ->where('msisdn', 'like', "%{$filters['q']}%")
                ->orWhere('name', 'like', "%{$filters['q']}%"))
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Contact $c) => [
                'id' => $c->id,
                'msisdn' => $c->msisdn,
                'name' => $c->name,
                'locale' => $c->locale,
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
        ]);
    }

    /** Promotional / bulk campaigns. */
    public function campaigns(Request $request)
    {
        $this->authorizeAccess($request);

        $page = PromotionalCampaign::query()
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (PromotionalCampaign $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'template_name' => $c->template_name,
                'status' => $c->status,
                'total_recipients' => $c->total_recipients,
                'scheduled_at' => optional($c->scheduled_at)->toDateTimeString(),
                'sent_at' => optional($c->sent_at)->toDateTimeString(),
                'created_at' => optional($c->created_at)->toDateTimeString(),
            ]);

        return Inertia::render('WaModule/Campaigns', [
            'page' => $page,
        ]);
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
                'contact_msisdn' => optional($c->contact)->msisdn,
                'status' => $c->status,
                'last_message_at' => optional($c->last_message_at)->toDateTimeString(),
            ]);

        return Inertia::render('WaModule/Conversations', [
            'page' => $page,
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
                'contact_msisdn' => optional($convo->contact)->msisdn,
                'status' => $convo->status,
            ],
            'messages' => $messages,
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
