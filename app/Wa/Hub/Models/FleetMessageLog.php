<?php

namespace App\Wa\Hub\Models;

use App\Wa\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FleetMessageLog extends Model
{
    protected $connection = 'wa';
    use HasFactory;

    protected $fillable = [
        'user_id',
        'from_number',
        'to_number',
        'message_body',
        'template_name',
        'alert_event',
        'meta_cost_usd',
        'meta_cost_kwd',
        'points_cost',
        'status',
        'provider_message_id',
    ];

    protected $casts = [
        'points_cost' => 'integer',
        'message_body' => 'array',
        'meta_cost_usd' => 'float',
        'meta_cost_kwd' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Joined message preview for the table.
     */
    public function getMessagePreviewAttribute(): string
    {
        $body = $this->message_body;

        if (is_array($body)) {
            return implode(' • ', $body);
        }

        return (string) $body;
    }

    /**
     * High-level alert/notification type.
     *
     * - Fuel reports → "Fuel Report"
     * - Fleet alerts (old + new templates) → Idling / Power Cut / Low Battery / Overstay / Fleet Alert
     * - Anything else → "Other"
     */
    public function getAlertTypeLabelAttribute(): string
    {
        if ($this->alert_event) {
            return match ($this->alert_event) {
                'fuel_report' => 'Fuel Report',
                'idling' => 'Idling Alert',
                'power_cut', 'power_off', 'power_disconnect' => 'Power Cut',
                'low_battery' => 'Low Battery',
                'geofence_overstay' => 'Geofence Overstay',
                'fleet_alert' => 'Fleet Alert',
                default => 'Other',
            };
        }

        $template = $this->template_name ?? '';

        // 1) Fuel weekly report templates
        if (str_starts_with($template, 'fleet_fuel_report_')) {
            return 'Fuel Report';
        }

        // 2) Non-fleet stuff (e.g. banner_welcome, other marketing templates)
        if (! str_starts_with($template, 'fleet_alert_')) {
            return 'Other';
        }

        // 3) Fleet alerts (old + new)
        $details = $this->extractDetailsText();

        $text = mb_strtolower($details);

        // Idling – English & Arabic variants
        if (
            str_contains($text, 'idling')
            || str_contains($text, 'تشغيل خامل')
        ) {
            return 'Idling Alert';
        }

        // Power cut / power off – English & Arabic
        if (
            str_contains($text, 'power cut')
            || str_contains($text, 'power off')
            || str_contains($text, 'فصل الكهرباء')
        ) {
            return 'Power Cut';
        }

        // Low battery – English & Arabic
        if (
            str_contains($text, 'low battery')
            || str_contains($text, 'بطارية منخفضة')
        ) {
            return 'Low Battery';
        }

        // Geofence overstay – English & Arabic
        if (
            str_contains($text, 'overstay')
            || str_contains($text, 'تجاوز مدة')
            || str_contains($text, 'duration:')
            || str_contains($text, 'مدة:')
        ) {
            return 'Geofence Overstay';
        }

        // Fallback for anything we can't classify
        return 'Fleet Alert';
    }

    /**
     * Extract the "details" part from message_body
     * so it works for both old (4 params) and new (5 params) templates.
     */
    protected function extractDetailsText(): string
    {
        $body = $this->message_body;

        if (is_array($body) && ! empty($body)) {
            // New templates (fleet_alert_*1):
            //   [ account, vehicle, time, site, detailsBlock ]
            //
            // Old templates (fleet_alert_*):
            //   [ driver/vehicle, group, time, "Location...\nStatus: ...\nVoltage: ..." ]
            //
            // In **both** cases, the LAST element is where the "interesting" stuff lives.
            $last = end($body);

            if (is_string($last)) {
                return $last;
            }

            // If the array structure is weird, join everything as fallback
            return implode("\n", array_map('strval', $body));
        }

        if (is_string($body)) {
            return $body;
        }

        return '';
    }
}
