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
            $type = $this->typeLabel($a->log_name, $a->subject_type);
            $name = $this->subjectName($props);

            return [
                'id' => $a->id,
                'type' => $type,
                'event' => $a->event,
                // A plain-language sentence: "Updated Vendor — Saja Gulf".
                'summary' => $this->summary($a->event, $type, $name),
                'record' => $name,
                'subject_id' => $a->subject_id,
                'causer_name' => $a->causer?->name ?? 'System',
                'is_system' => ! $a->causer,
                'ip' => $props['ip'] ?? null,
                'created_at' => $a->created_at?->format('Y-m-d'),
                'created_time' => $a->created_at?->format('H:i'),
                'changes' => $this->changesPreview($props),
            ];
        });

        // Friendly { value, label } pairs for the type filter (machine log_name → readable).
        $logNames = Activity::query()->select('log_name')->whereNotNull('log_name')
            ->distinct()->orderBy('log_name')->pluck('log_name')
            ->map(fn ($ln) => ['value' => $ln, 'label' => $this->typeLabel($ln, null)])
            ->all();

        return Inertia::render('ActivityLog/Index', [
            'filters' => $filters,
            'page' => $page,
            'log_names' => $logNames,
            'events' => ['created', 'updated', 'deleted', 'restored'],
            'counts' => [
                'total' => Activity::query()->count(),
            ],
        ]);
    }

    /** Human label for the record type, e.g. "journalentry" → "Journal Entry". */
    protected function typeLabel(?string $logName, ?string $subjectType): string
    {
        if ($subjectType) {
            return (string) Str::of(class_basename($subjectType))->headline();
        }

        return $logName ? (string) Str::of($logName)->headline() : 'Record';
    }

    /** Best-effort human name for the affected record, read straight from the logged payload. */
    protected function subjectName(array $props): ?string
    {
        $bag = ($props['attributes'] ?? []) + ($props['old'] ?? []);
        foreach (['name', 'title', 'full_name', 'label', 'code', 'reference', 'number', 'narration', 'email'] as $key) {
            if (! empty($bag[$key])) {
                return $this->scalar($bag[$key]);
            }
        }

        return null;
    }

    /** A plain-language sentence describing the action. */
    protected function summary(?string $event, string $type, ?string $name): string
    {
        $verb = ['created' => 'Added', 'updated' => 'Updated', 'deleted' => 'Deleted', 'restored' => 'Restored'][$event] ?? ucfirst((string) $event);

        return trim($verb.' '.$type.($name ? ' — '.$name : ''));
    }

    /** Compact, humanized "field: old → new" list from the Spatie properties payload. */
    protected function changesPreview(array $props): array
    {
        $new = $props['attributes'] ?? [];
        $old = $props['old'] ?? [];

        // Hide technical plumbing that means nothing to a normal user.
        $hidden = ['id', 'guard_name', 'remember_token', 'password', 'created_at', 'updated_at', 'deleted_at', 'email_verified_at'];
        $keys = array_values(array_diff(array_keys($new ?: $old), $hidden));
        $keys = array_slice($keys, 0, 12);

        $out = [];
        foreach ($keys as $k) {
            $out[] = [
                'field' => $this->fieldLabel($k),
                'old' => array_key_exists($k, $old) ? $this->scalar($old[$k]) : null,
                'new' => array_key_exists($k, $new) ? $this->scalar($new[$k]) : null,
            ];
        }

        return $out;
    }

    /** "default_account_id" → "Default account". */
    protected function fieldLabel(string $key): string
    {
        $key = preg_replace('/_id$/', '', $key);

        return ucfirst((string) Str::of($key)->replace('_', ' ')->lower());
    }

    /** Render any stored value as a short, human-readable string. */
    protected function scalar(mixed $v): string
    {
        if (is_null($v) || $v === '') {
            return '—';
        }
        if (is_bool($v)) {
            return $v ? 'Yes' : 'No';
        }
        if (is_array($v)) {
            // Localized {"en":..,"ar":..} → prefer English; else compact JSON.
            if (isset($v['en']) || isset($v['ar'])) {
                return (string) ($v['en'] ?? $v['ar'] ?? '');
            }

            return Str::limit((string) json_encode($v, JSON_UNESCAPED_UNICODE), 60, '…');
        }

        return Str::limit((string) $v, 60, '…');
    }
}
