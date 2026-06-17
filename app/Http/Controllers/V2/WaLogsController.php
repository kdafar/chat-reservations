<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\WAMessageLog;
use App\Models\WhatsappSession;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WhatsApp monitoring — read-only v2 replacements for the Filament
 * WAMessageLogResource and WhatsappSessionResource. Inbound/outbound message
 * log + live conversation sessions. Admin-only, view-only.
 */
class WaLogsController extends Controller
{
    protected function authorizeAccess(Request $request, string $permission = 'view_any_wa_message_logs'): void
    {
        if (! $request->user() || ! $request->user()->can($permission)) {
            abort(403, 'Admin access required.');
        }
    }

    /** Styled .xlsx export (mirrors the list filters). */
    public function export(Request $request)
    {
        $this->authorizeAccess($request);
        $q = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $query = \App\Models\WAMessageLog::query();
        if ($q !== '') { $query->where(fn ($w) => $w->where('phone', 'like', "%{$q}%")->orWhere('wa_message_id', 'like', "%{$q}%")); }
        if ($status !== '') { $query->where('status', $status); }
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\V2\StyledQueryExport(
                $query->orderByDesc('id'),
                ['ID', 'WA message ID', 'Phone', 'Status', 'Created at'],
                fn ($l) => [$l->id, $l->wa_message_id, $l->phone, $l->status, optional($l->created_at)->format('Y-m-d H:i')],
                'WhatsApp Logs',
                app()->getLocale() === 'ar',
            ),
            'wa-logs-'.now()->format('Ymd-His').'.xlsx',
        );
    }

        public function logs(Request $request): Response
    {
        $this->authorizeAccess($request);
        $filters = ['q' => trim((string) $request->input('q', '')), 'status' => trim((string) $request->input('status', ''))];

        $query = WAMessageLog::query();
        if ($filters['q'] !== '') {
            $query->where(fn ($w) => $w->where('phone', 'like', "%{$filters['q']}%")->orWhere('wa_message_id', 'like', "%{$filters['q']}%"));
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderByDesc('id')->paginate(30)->withQueryString();
        $page->getCollection()->transform(fn (WAMessageLog $l) => [
            'id' => $l->id, 'wa_message_id' => $l->wa_message_id, 'phone' => $l->phone, 'status' => $l->status,
            'created_at' => optional($l->created_at)->format('Y-m-d h:i A'),
            'payload' => $l->payload,
        ]);

        return Inertia::render('Whatsapp/Logs', [
            'filters' => $filters,
            'page' => $page,
            'statuses' => WAMessageLog::query()->select('status')->distinct()->whereNotNull('status')->pluck('status')->all(),
        ]);
    }

    public function sessions(Request $request): Response
    {
        $this->authorizeAccess($request, 'view_any_whatsapp_session');
        $filters = ['q' => trim((string) $request->input('q', '')), 'status' => trim((string) $request->input('status', ''))];

        $query = WhatsappSession::query()->with(['provider:id,name', 'serviceType:id,name_en']);
        if ($filters['q'] !== '') {
            $query->where('phone', 'like', "%{$filters['q']}%");
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        $page = $query->orderByDesc('last_interacted_at')->paginate(30)->withQueryString();
        $page->getCollection()->transform(fn (WhatsappSession $s) => [
            'id' => $s->id, 'phone' => $s->phone, 'status' => $s->status, 'locale' => $s->locale,
            'provider' => $s->provider?->name, 'service_type' => $s->serviceType?->name_en,
            'current_screen' => $s->current_screen,
            'last_interacted_at' => optional($s->last_interacted_at)->format('Y-m-d h:i A'),
            'context' => $s->context,
        ]);

        return Inertia::render('Whatsapp/Sessions', [
            'filters' => $filters,
            'page' => $page,
            'statuses' => WhatsappSession::query()->select('status')->distinct()->whereNotNull('status')->pluck('status')->all(),
        ]);
    }
}
