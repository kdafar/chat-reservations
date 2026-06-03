<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\MessageText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Message Catalog (MessageText) — v2 replacement for the Filament
 * MessageTextResource. Keyed, locale-aware copy strings used across the app.
 * Admin-only.
 */
class MessageTextsController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Admin access required.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $filters = ['q' => trim((string) $request->input('q', '')), 'locale' => $request->input('locale', 'all')];

        $query = MessageText::query();
        if ($filters['q'] !== '') {
            $query->where(fn ($w) => $w->where('key', 'like', "%{$filters['q']}%")->orWhere('value', 'like', "%{$filters['q']}%"));
        }
        if (in_array($filters['locale'], ['en', 'ar'], true)) {
            $query->where('locale', $filters['locale']);
        }

        $page = $query->orderBy('key')->paginate(30)->withQueryString();
        $page->getCollection()->transform(fn (MessageText $m) => [
            'id' => $m->id, 'key' => $m->key, 'locale' => $m->locale, 'value' => $m->value,
            'updated_at' => optional($m->updated_at)->diffForHumans(),
        ]);

        return Inertia::render('Whatsapp/MessageTexts', ['filters' => $filters, 'page' => $page]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        MessageText::create($this->validateData($request, null));

        return back()->with('flash', ['type' => 'success', 'message' => 'Message saved.']);
    }

    public function update(Request $request, MessageText $messageText): RedirectResponse
    {
        $this->authorizeAccess($request);
        $messageText->update($this->validateData($request, $messageText));

        return back()->with('flash', ['type' => 'success', 'message' => 'Message updated.']);
    }

    public function destroy(Request $request, MessageText $messageText): RedirectResponse
    {
        $this->authorizeAccess($request);
        $messageText->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Message deleted.']);
    }

    protected function validateData(Request $request, ?MessageText $existing): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:191', Rule::unique('message_texts')->where(fn ($q) => $q->where('locale', $request->input('locale')))->ignore($existing?->id)],
            'locale' => ['required', 'in:en,ar'],
            'value' => ['required', 'string', 'max:5000'],
        ]);
    }
}
