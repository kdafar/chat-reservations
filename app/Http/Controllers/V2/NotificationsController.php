<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationsController extends Controller
{
    /**
     * Light-weight polling endpoint for the v2 NotificationPoller.
     *
     * Query params:
     *   - since: ISO-8601 timestamp. Only notifications created strictly
     *            AFTER this moment are returned. If omitted, we return
     *            counts only (no payloads) so the first call doesn't
     *            replay history.
     *
     * Response shape:
     *   {
     *     "unread_count": int,
     *     "new": [
     *       { id, title, body, url, icon, kind, created_at }
     *     ],
     *     "cursor": "ISO-8601"   // pass this back as ?since= on the next call
     *   }
     */
    public function poll(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'unread_count' => 0,
                'new' => [],
                'cursor' => now()->toIso8601String(),
            ]);
        }

        $unread = (int) DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $since = $request->query('since');
        $sinceAt = null;
        try {
            $sinceAt = $since ? Carbon::parse($since) : null;
        } catch (\Throwable) {
            $sinceAt = null;
        }

        // First poll (no since): just return the count + a cursor pinned to
        // "now" so we don't replay old unread items as toasts.
        if (! $sinceAt) {
            return response()->json([
                'unread_count' => $unread,
                'new' => [],
                'cursor' => now()->toIso8601String(),
            ]);
        }

        $rows = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->where('created_at', '>', $sinceAt)
            ->orderBy('created_at')
            ->limit(10)
            ->get(['id', 'data', 'created_at']);

        $new = $rows->map(function ($row) {
            $data = json_decode($row->data, true) ?: [];
            $iconRaw = (string) ($data['icon'] ?? 'bell');

            return [
                'id' => $row->id,
                'title' => (string) ($data['title'] ?? ''),
                'body' => (string) ($data['body'] ?? ''),
                'url' => $this->extractActionUrl($data),
                'icon' => $this->normalizeIcon($iconRaw),
                'kind' => $this->normalizeKind((string) ($data['iconColor'] ?? 'primary')),
                'created_at' => Carbon::parse($row->created_at)->toIso8601String(),
            ];
        })->values();

        $cursor = $rows->isNotEmpty()
            ? Carbon::parse($rows->last()->created_at)->toIso8601String()
            : $sinceAt->toIso8601String();

        return response()->json([
            'unread_count' => $unread,
            'new' => $new,
            'cursor' => $cursor,
        ]);
    }

    /**
     * Most-recent ~15 notifications (read + unread) for the bell popover.
     */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['items' => [], 'unread_count' => 0]);
        }

        $rows = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'data', 'read_at', 'created_at']);

        $items = $rows->map(function ($row) {
            $data = json_decode($row->data, true) ?: [];

            return [
                'id' => $row->id,
                'title' => (string) ($data['title'] ?? ''),
                'body' => (string) ($data['body'] ?? ''),
                'url' => $this->extractActionUrl($data),
                'icon' => $this->normalizeIcon((string) ($data['icon'] ?? 'bell')),
                'kind' => $this->normalizeKind((string) ($data['iconColor'] ?? 'primary')),
                'read' => ! is_null($row->read_at),
                'created_at' => Carbon::parse($row->created_at)->toIso8601String(),
            ];
        });

        $unread = (int) DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'items' => $items,
            'unread_count' => $unread,
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false], 403);
        }

        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['ok' => false], 403);
        }

        DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['ok' => true]);
    }

    protected function extractActionUrl(array $data): ?string
    {
        foreach ((array) ($data['actions'] ?? []) as $action) {
            if (! empty($action['url'])) {
                return $this->rewriteToV2((string) $action['url']);
            }
        }

        return null;
    }

    /**
     * Notifications were historically created with links into the old Filament
     * panel (/admin/...). The v2 popover/poller navigates straight to whatever
     * URL we return, so rewrite those targets to their v2 equivalents — this
     * fixes rows already stored in the DB as well as any Filament-created ones.
     * New app notifications already emit /admin/v2/ URLs and pass through untouched.
     */
    protected function rewriteToV2(string $url): string
    {
        if ($url === '' || str_contains($url, '/admin/v2/')) {
            return $url;
        }

        // Match on the PATH only — some notifications store absolute URLs (built via
        // Laravel's url() helper, e.g. https://host/admin/bookings/12/edit), so anchoring
        // on the raw string would miss the scheme+host prefix.
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return $url;
        }

        // Filament resource pages whose v2 equivalent is a list page that deep-links
        // to a record via ?open={id} (the v2 page opens that record on load).
        if (preg_match('#^/admin/insurance/preauthorizations/(\d+)(?:/edit)?$#', $path, $m)) {
            return "/admin/v2/insurance/preauthorizations?open={$m[1]}";
        }
        if (preg_match('#^/admin/clinic-items/(\d+)(?:/edit)?$#', $path, $m)) {
            return "/admin/v2/clinic-items?open={$m[1]}";
        }
        if (preg_match('#^/admin/bookings/(\d+)(?:/edit)?$#', $path, $m)) {
            return "/admin/v2/bookings?open={$m[1]}";
        }

        // Filament resource paths -> v2 index pages (no per-record deep link).
        $indexRules = [
            '#^/admin/insurance/preauthorizations/?$#'           => '/admin/v2/insurance/preauthorizations',
            '#^/admin/clinic-items/?$#'                          => '/admin/v2/clinic-items',
            '#^/admin/clinic-item-stocks?(?:/\d+(?:/edit)?)?$#'  => '/admin/v2/clinic-stock',
            '#^/admin/visit-stock-requests(?:/\d+(?:/edit)?)?$#' => '/admin/v2/visit-stock-requests',
            '#^/admin/bookings/?$#'                              => '/admin/v2/bookings',
        ];
        foreach ($indexRules as $pattern => $replacement) {
            if (preg_match($pattern, $path)) {
                return $replacement;
            }
        }

        // Entities whose v2 page keeps the record id (show pages).
        if (preg_match('#^/admin/visits/(\d+)(?:/edit)?$#', $path, $m)) {
            return "/admin/v2/visits/{$m[1]}";
        }
        if (preg_match('#^/admin/patients/(\d+)(?:/edit)?$#', $path, $m)) {
            return "/admin/v2/patients/{$m[1]}";
        }

        return $url;
    }

    /** Map Filament's heroicon name to a Lucide name our Icon component knows. */
    protected function normalizeIcon(string $name): string
    {
        $name = preg_replace('/^heroicon-[osm]-/', '', $name) ?? $name;

        $map = [
            'calendar-days' => 'calendar-check',
            'banknotes' => 'credit-card',
            'currency-dollar' => 'credit-card',
            'check-circle' => 'check-circle-2',
            'exclamation-triangle' => 'alert-triangle',
            'exclamation-circle' => 'alert-circle',
            'information-circle' => 'info',
        ];

        return $map[$name] ?? $name;
    }

    /** Filament iconColor → our toast kind. */
    protected function normalizeKind(string $color): string
    {
        return match ($color) {
            'success', 'green', 'emerald' => 'success',
            'warning', 'amber', 'yellow' => 'warning',
            'danger', 'destructive', 'red', 'rose' => 'warning',
            'info', 'blue', 'sky' => 'info',
            default => 'primary',
        };
    }
}
