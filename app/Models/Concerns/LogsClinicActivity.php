<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Drop-in audit-trail trait for clinic-domain models.
 *
 * Uses Spatie\Activitylog with a sensible default config:
 *   - logs all attributes (most clinic models use $guarded = [] so $fillable
 *     would log nothing — logAll() is the right default here)
 *   - only writes a row when fields actually changed (no-op saves are silent)
 *   - omits empty diffs from the persisted payload
 *
 * Override `$activityLogName` to bucket entries (e.g. "patients") and
 * `$activityLogExcept` to drop noisy fields (timestamps, large JSON, etc).
 */
trait LogsClinicActivity
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        $name = property_exists($this, 'activityLogName') && $this->activityLogName
            ? (string) $this->activityLogName
            : strtolower(class_basename(static::class));

        $except = property_exists($this, 'activityLogExcept') && is_array($this->activityLogExcept)
            ? $this->activityLogExcept
            : [];

        // Default exclusions every model can safely skip — these change on
        // every save and aren't useful diffs in an audit log.
        $defaultExcept = ['created_at', 'updated_at', 'deleted_at'];

        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontLogIfAttributesChangedOnly(['updated_at'])
            ->useLogName($name)
            ->dontSubmitEmptyLogs()
            ->logExcept(array_values(array_unique(array_merge($defaultExcept, $except))));
    }
}
