<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\WACommand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WhatsApp Commands — v2 replacement for the Filament WACommandResource.
 *
 * Keyword shortcuts the bot recognises in a chat (reset, start, menu, jump-to-
 * state). Admin-only.
 */
class WaCommandsController extends Controller
{
    protected const ACTIONS = ['reset' => 'Reset & Start', 'start' => 'Start', 'menu' => 'Show Menu/Help', 'jump' => 'Jump to State'];

    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRole(['admin', 'super_admin'])) {
            abort(403, 'Admin access required.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = ['q' => trim((string) $request->input('q', '')), 'language' => $request->input('language', 'all')];

        $query = WACommand::query();
        if ($filters['q'] !== '') {
            $query->where('keyword', 'like', "%{$filters['q']}%");
        }
        if (in_array($filters['language'], ['en', 'ar'], true)) {
            $query->where('language', $filters['language']);
        }

        $page = $query->orderByDesc('priority')->orderBy('keyword')->paginate(25)->withQueryString();
        $page->getCollection()->transform(fn (WACommand $c) => [
            'id' => $c->id, 'keyword' => $c->keyword, 'language' => $c->language, 'action' => $c->action,
            'jump_state' => data_get($c->params, 'state'), 'priority' => $c->priority, 'enabled' => (bool) $c->enabled,
        ]);

        return Inertia::render('Whatsapp/Commands', [
            'filters' => $filters,
            'page' => $page,
            'actions' => collect(self::ACTIONS)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values()->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        $c = new WACommand();
        $this->fill($c, $this->validateData($request));
        $c->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Command created.']);
    }

    public function update(Request $request, WACommand $waCommand): RedirectResponse
    {
        $this->authorizeAccess($request);
        $this->fill($waCommand, $this->validateData($request));
        $waCommand->save();

        return back()->with('flash', ['type' => 'success', 'message' => 'Command updated.']);
    }

    public function destroy(Request $request, WACommand $waCommand): RedirectResponse
    {
        $this->authorizeAccess($request);
        $waCommand->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Command deleted.']);
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'keyword' => ['required', 'string', 'max:191'],
            'language' => ['required', 'in:en,ar'],
            'action' => ['required', 'in:reset,start,menu,jump'],
            'jump_state' => ['nullable', 'required_if:action,jump', 'string', 'max:191'],
            'priority' => ['required', 'integer', 'min:0', 'max:9999'],
            'enabled' => ['boolean'],
        ]);
    }

    protected function fill(WACommand $c, array $data): void
    {
        $c->keyword = $data['keyword'];
        $c->language = $data['language'];
        $c->action = $data['action'];
        $c->params = $data['action'] === 'jump' ? ['state' => $data['jump_state']] : null;
        $c->priority = $data['priority'];
        $c->enabled = (bool) ($data['enabled'] ?? false);
    }
}
