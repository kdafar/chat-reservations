<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\WhatsappTrigger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WhatsApp Triggers — v2 replacement for the Filament WhatsappTriggerResource.
 *
 * The bot's auto-reply rules: a trigger (keyword / welcome / finale / fallback)
 * maps to a response of one of several shapes (text, link, image, document,
 * buttons, list, template, flow). The per-shape config lives in the
 * `response_meta` JSON; the Vue editor sends it back as a structured object.
 * Admin-only.
 */
class WhatsappTriggersController extends Controller
{
    protected const TYPES = ['keyword', 'welcome', 'finale', 'fallback'];

    protected const RESPONSE_TYPES = ['text', 'link', 'image_upload', 'document_upload', 'buttons', 'list', 'template', 'flow'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Admin access required.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'type' => $request->input('type', 'all'),
            'response_type' => $request->input('response_type', 'all'),
        ];

        $query = WhatsappTrigger::query();
        if ($filters['q'] !== '') {
            $query->where(fn ($w) => $w->where('keyword', 'like', "%{$filters['q']}%")
                ->orWhere('response_message_en', 'like', "%{$filters['q']}%"));
        }
        if (in_array($filters['type'], self::TYPES, true)) {
            $query->where('type', $filters['type']);
        }
        if (in_array($filters['response_type'], self::RESPONSE_TYPES, true)) {
            $query->where('response_type', $filters['response_type']);
        }

        $page = $query->orderByDesc('updated_at')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (WhatsappTrigger $r) => $this->present($r));

        return Inertia::render('Whatsapp/Triggers', [
            'filters' => $filters,
            'page' => $page,
            'counts' => [
                'total' => WhatsappTrigger::query()->count(),
                'active' => WhatsappTrigger::query()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $trigger = new WhatsappTrigger();
        $this->fill($trigger, $this->validateData($request), $request, null);
        $trigger->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Trigger created.']);
    }

    public function update(Request $request, WhatsappTrigger $whatsappTrigger): RedirectResponse
    {
        $this->authorizeAccess($request);
        $this->fill($whatsappTrigger, $this->validateData($request), $request, $whatsappTrigger);
        $whatsappTrigger->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Trigger updated.']);
    }

    public function destroy(Request $request, WhatsappTrigger $whatsappTrigger): RedirectResponse
    {
        $this->authorizeAccess($request);
        $whatsappTrigger->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Trigger deleted.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:keyword,welcome,finale,fallback'],
            'keyword' => ['nullable', 'array'],
            'keyword.*' => ['string', 'max:191'],
            'response_type' => ['required', 'in:text,link,image_upload,document_upload,buttons,list,template,flow'],
            'is_active' => ['boolean'],
            'response_message_en' => ['nullable', 'string', 'max:4000'],
            'response_message_ar' => ['nullable', 'string', 'max:4000'],
            'response_meta' => ['nullable', 'array'],
            'media' => ['nullable', 'file', 'max:8192'],
        ]);
    }

    protected function fill(WhatsappTrigger $t, array $data, Request $request, ?WhatsappTrigger $existing): void
    {
        $t->type = $data['type'];
        // Keywords stored as a comma+space CSV string (matches the bot's reader).
        $t->keyword = ($data['type'] === 'keyword' && ! empty($data['keyword']))
            ? implode(', ', array_values(array_unique(array_filter(array_map('trim', $data['keyword'])))))
            : null;
        $t->response_type = $data['response_type'];
        $t->is_active = (bool) ($data['is_active'] ?? false);
        $t->response_message_en = $data['response_message_en'] ?? null;
        $t->response_message_ar = $data['response_message_ar'] ?? null;

        $meta = $data['response_meta'] ?? [];

        // Handle a freshly uploaded media file for image/document responses;
        // otherwise keep whatever path was already stored.
        if ($request->hasFile('media')) {
            $path = $request->file('media')->store('whatsapp-media', 'public');
            if ($data['response_type'] === 'image_upload') {
                $meta['image_upload_path'] = $path;
            } elseif ($data['response_type'] === 'document_upload') {
                $meta['document_upload_path'] = $path;
            }
        } else {
            $existingMeta = is_array($existing?->response_meta) ? $existing->response_meta : [];
            foreach (['image_upload_path', 'document_upload_path'] as $k) {
                if (empty($meta[$k]) && ! empty($existingMeta[$k])) {
                    $meta[$k] = $existingMeta[$k];
                }
            }
        }

        $t->response_meta = $meta ?: null;
    }

    protected function present(WhatsappTrigger $t): array
    {
        $meta = is_array($t->response_meta) ? $t->response_meta : [];
        foreach (['image_upload_path', 'document_upload_path'] as $k) {
            if (! empty($meta[$k])) {
                $meta[$k.'_url'] = Storage::disk('public')->url($meta[$k]);
            }
        }

        return [
            'id' => $t->id,
            'type' => $t->type,
            'keyword' => $t->keyword ? array_values(array_filter(array_map('trim', explode(',', $t->keyword)))) : [],
            'response_type' => $t->response_type,
            'is_active' => (bool) $t->is_active,
            'response_message_en' => $t->response_message_en,
            'response_message_ar' => $t->response_message_ar,
            'response_meta' => $meta,
            'updated_at' => optional($t->updated_at)->diffForHumans(),
        ];
    }
}
