<?php

namespace App\Http\Controllers\V2;

use App\Filament\Resources\SystemSettingResource;
use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * System Settings — v2 replacement for Filament SystemSettingResource.
 * Presents the fixed allowedKeys() set as one typed form (bool/int/text/secret).
 * Secrets are never echoed back to the client — only an "is_set" flag — and are
 * left untouched on save unless a new value is entered.
 */
class SystemSettingsController extends Controller
{
    protected function authorizeAccess(Request $request): void
    {
        if (! $request->user() || ! $request->user()->can('view_any_system_setting')) {
            abort(403, 'Not authorized to view settings.');
        }
    }

    protected function canEdit(Request $request): bool
    {
        return (bool) $request->user()?->can('update_system_setting');
    }

    public function index(Request $request): Response
    {
        $this->authorizeAccess($request);

        $defs = SystemSettingResource::allowedKeys();
        $stored = SystemSetting::query()->whereIn('key', array_keys($defs))->pluck('value', 'key');

        $fields = [];
        foreach ($defs as $key => $def) {
            $type = $def['type'] ?? 'text';
            $raw = $stored->get($key);
            $fields[] = [
                'key' => $key,
                'type' => $type,
                'label' => $def['label'] ?? $key,
                'placeholder' => $def['placeholder'] ?? null,
                'group' => $this->groupFor($key),
                // Secrets: never send the value, just whether one is set.
                'value' => $type === 'secret' ? null : $this->scalar($raw, $type),
                'is_set' => $raw !== null && $raw !== '',
            ];
        }

        return Inertia::render('Settings/Index', [
            'fields' => $fields,
            'can_edit' => $this->canEdit($request),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAccess($request);
        if (! $this->canEdit($request)) abort(403);

        $defs = SystemSettingResource::allowedKeys();
        $input = (array) $request->input('values', []);

        foreach ($input as $key => $value) {
            if (! array_key_exists($key, $defs)) {
                continue; // ignore unknown keys
            }
            $type = $defs[$key]['type'] ?? 'text';

            // Secrets: skip when left blank so we don't wipe a stored token.
            if ($type === 'secret' && ($value === null || $value === '')) {
                continue;
            }

            $cast = match ($type) {
                'bool' => (bool) $value,
                'int' => $value === null || $value === '' ? null : (int) $value,
                default => $value === null ? '' : (string) $value,
            };

            SystemSetting::updateOrCreate(['key' => $key], ['value' => $cast]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Settings saved.']);
    }

    /** Coerce a stored (array-cast) value back to a scalar for the form input. */
    protected function scalar($raw, string $type)
    {
        if (is_array($raw)) {
            $raw = $raw[0] ?? null;
        }
        return match ($type) {
            'bool' => (bool) $raw,
            'int' => $raw === null ? null : (int) $raw,
            default => $raw === null ? '' : (string) $raw,
        };
    }

    /** Coarse grouping for the settings UI. */
    protected function groupFor(string $key): string
    {
        if (str_starts_with($key, 'whatsapp.template')) return 'WhatsApp Templates';
        if (str_starts_with($key, 'whatsapp.')) return 'WhatsApp API';
        return 'General';
    }
}
