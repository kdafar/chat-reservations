<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\WAMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WhatsApp Message templates (WAMessage) — v2 replacement for the Filament
 * WAMessageResource. Keyed bilingual bot messages with {placeholder} tokens.
 * Admin-only.
 */
class WaMessagesController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Admin access required.');
        }
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $language = $request->input('language', 'all');
        $query = \App\Models\WAMessage::query();
        if ($q !== '') { $query->where(fn ($w) => $w->where('key', 'like', "%{$q}%")->orWhere('text', 'like', "%{$q}%")); }
        if (in_array($language, ['en', 'ar'], true)) { $query->where('language', $language); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderBy('key'),
                ['ID', 'Key', 'Language', 'Text', 'Enabled', 'Updated at'],
                fn ($m) => [$m->id, $m->key, $m->language, $m->text, $m->enabled ? 'Yes' : 'No', optional($m->updated_at)->format('Y-m-d H:i')],
                'WhatsApp Messages',
                app()->getLocale() === 'ar',
            ),
            'wa-messages-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function index(Request $request): Response
    {
        $this->authorizeAccess($request);
        $filters = ['q' => trim((string) $request->input('q', '')), 'language' => $request->input('language', 'all')];

        $query = WAMessage::query();
        if ($filters['q'] !== '') {
            $query->where(fn ($w) => $w->where('key', 'like', "%{$filters['q']}%")->orWhere('text', 'like', "%{$filters['q']}%"));
        }
        if (in_array($filters['language'], ['en', 'ar'], true)) {
            $query->where('language', $filters['language']);
        }

        $page = $query->orderBy('key')->paginate(30)->withQueryString();
        $page->getCollection()->transform(fn (WAMessage $m) => [
            'id' => $m->id, 'key' => $m->key, 'language' => $m->language, 'text' => $m->text, 'enabled' => (bool) $m->enabled,
            'updated_at' => optional($m->updated_at)->diffForHumans(),
        ]);

        return Inertia::render('Whatsapp/Messages', ['filters' => $filters, 'page' => $page]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        WAMessage::create($this->validateData($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Template saved.']);
    }

    public function update(Request $request, WAMessage $waMessage): RedirectResponse
    {
        $this->authorizeAccess($request);
        $waMessage->update($this->validateData($request));

        return back()->with('flash', ['type' => 'success', 'message' => 'Template updated.']);
    }

    public function destroy(Request $request, WAMessage $waMessage): RedirectResponse
    {
        $this->authorizeAccess($request);
        $waMessage->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Template deleted.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:191'],
            'language' => ['required', 'in:en,ar'],
            'text' => ['required', 'string', 'max:5000'],
            'enabled' => ['boolean'],
        ]);
    }
}
