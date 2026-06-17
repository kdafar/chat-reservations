<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Activity Log — v2 replacement for Filament ActivityResource.
 * Admin / super_admin only (mirrors the Filament canAccess()). Read-only.
 */
class ActivityLogController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_activity_log')) {
            abort(403, 'Not authorized to view the activity log.');
        }
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $filters = [
            'q' => trim((string) $request->input('q', '')),
            'log_name' => $request->input('log_name', 'all'),
            'event' => $request->input('event', 'all'),
            'from' => $request->input('from'),
            'until' => $request->input('until'),
        ];

        $query = Activity::query()->with('causer:id,name');

        if ($filters['q'] !== '') {
            $q = $filters['q'];
            $query->where(fn ($qq) => $qq->where('description', 'like', "%{$q}%")
                ->orWhere('subject_type', 'like', "%{$q}%"));
        }
        if ($filters['log_name'] !== 'all' && $filters['log_name'] !== '') {
            $query->where('log_name', $filters['log_name']);
        }
        if (in_array($filters['event'], ['created', 'updated', 'deleted', 'restored'], true)) {
            $query->where('event', $filters['event']);
        }
        if ($filters['from']) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['until']) {
            $query->whereDate('created_at', '<=', $filters['until']);
        }

        $page = $query->orderByDesc('id')->paginate(30)->withQueryString();
        $page->getCollection()->transform(function (Activity $a) {
            $props = is_array($a->properties) ? $a->properties : ($a->properties?->toArray() ?? []);
            return [
                'id' => $a->id,
                'log_name' => $a->log_name,
                'event' => $a->event,
                'description' => $a->description,
                'subject_label' => $a->subject_type
                    ? class_basename($a->subject_type).' #'.$a->subject_id
                    : '—',
                'causer_name' => $a->causer?->name ?? '— system',
                'created_at' => $a->created_at?->format('Y-m-d H:i:s'),
                'changes' => $this->changesPreview($props),
            ];
        });

        return Inertia::render('ActivityLog/Index', [
            'filters' => $filters,
            'page' => $page,
            'log_names' => Activity::query()->select('log_name')->whereNotNull('log_name')
                ->distinct()->orderBy('log_name')->pluck('log_name')->all(),
            'events' => ['created', 'updated', 'deleted', 'restored'],
            'counts' => [
                'total' => Activity::query()->count(),
            ],
        ]);
    }

    /** Compact "field: old → new" list from the Spatie properties payload. */
    protected function changesPreview(array $props): array
    {
        $new = $props['attributes'] ?? [];
        $old = $props['old'] ?? [];
        $keys = array_slice(array_keys($new ?: $old), 0, 12);

        $out = [];
        foreach ($keys as $k) {
            $out[] = [
                'field' => $k,
                'old' => array_key_exists($k, $old) ? Str::limit((string) json_encode($old[$k], JSON_UNESCAPED_UNICODE), 40, '…') : null,
                'new' => array_key_exists($k, $new) ? Str::limit((string) json_encode($new[$k], JSON_UNESCAPED_UNICODE), 40, '…') : null,
            ];
        }
        return $out;
    }
}
