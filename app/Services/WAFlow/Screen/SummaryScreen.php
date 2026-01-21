<?php

namespace App\Services\WAFlow\Screen;

use App\Models\Branch;
use App\Models\ReservationTerm; // ⬅️ NEW
use App\Models\WhatsappContact;
use App\Models\WhatsappSession;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\HoldService;
use App\Services\WAFlow\FlowCtx;
use App\Services\WAFlow\FlowLocalization;
use App\Services\WAFlow\FlowRequest;
use Illuminate\Support\Facades\Log;

class SummaryScreen
{
    public function __construct(
        private FlowCtx $ctx,                    // DB-backed state (whatsapp_flow_states)
        private HoldService $holds,
        private BookingService $bookings,
        private AvailabilityService $availability,
        private FlowLocalization $i18n,
    ) {}

    public function exchange(FlowRequest $req, ?WhatsappSession $session, string $locale): array
    {
        $flowToken = $req->flowToken;
        $c = (array) ($this->ctx->all($flowToken) ?? []);
        $trigger = (string) ($req->data['trigger'] ?? '');

        // 0) Back → DETAILS (keep everything intact)
        if ($trigger === 'back' || $trigger === 'back_to_details') {
            return [
                '__nav' => 'DETAILS',
                '__data' => [
                    // Prefill DETAILS with what we already have
                    'name' => (string) ($c['name'] ?? ''),
                    'phone' => (string) ($c['phone'] ?? ($session?->phone ?? '')),
                    'email' => (string) ($c['email'] ?? ''),
                    'notes' => (string) ($c['notes'] ?? ''),
                    'branch' => (string) ($c['branch_id'] ?? ''),
                    'party_size' => (string) ($c['party_size'] ?? ''),
                    'date' => (string) ($c['res_date'] ?? ''),
                    'time' => (string) ($c['res_time'] ?? ''),
                ],
            ];
        }

        // 1) Initial render (no trigger) → show SUMMARY from ctx
        if ($trigger === '') {
            $payload = $this->buildSummaryPayload($c, $locale);

            return $this->frame($req, 'SUMMARY', $payload);
        }

        // 2) Confirm → validate terms (if required), validate hold, confirm booking
        if ($trigger === 'confirm_booking') {
            // Latest snapshot from ctx (SUMMARY form may not send all fields)
            $branchId = (int) ($c['branch_id'] ?? 0);
            $party = (int) ($c['party_size'] ?? 0);
            $dateStr = (string) ($c['res_date'] ?? '');
            $timeStr = (string) ($c['res_time'] ?? '');
            $slotKey = (string) ($c['slot_key'] ?? '');

            // 2.a) Terms validation (admin-managed)
            $agree = filter_var(($req->data['agree_terms'] ?? false), FILTER_VALIDATE_BOOLEAN);
            $terms = ReservationTerm::forBranch($branchId);
            $require = (bool) ($terms?->terms_required ?? false);

            if ($require && ! $agree) {
                $payload = $this->buildSummaryPayload($c, $locale);
                $payload['terms_error'] = $locale === 'ar'
                    ? 'يجب الموافقة على الشروط لإتمام الحجز.'
                    : 'You must accept the terms to confirm the booking.';
                // ensure label/text are from DB
                $payload['terms_label'] = $terms?->label($locale) ?? $payload['terms_label'];
                $payload['terms_text'] = $terms?->text($locale) ?? $payload['terms_text'];
                $payload['terms_required'] = true;

                return $this->frame($req, 'SUMMARY', $payload);
            }

            // 2.b) If no valid hold, try to recover (user may have hopped back & forth)
            $hold = $slotKey ? $this->holds->findActive($slotKey) : null;
            if (! $hold) {
                if (! $branchId || ! $party || $dateStr === '' || $timeStr === '') {
                    $payload = app(AppointmentScreen::class)->build($session, $locale, $flowToken);
                    $payload['confirmation_message'] = $this->i18n->tr('pick_again', $locale);

                    return $this->frame($req, 'APPOINTMENT', $payload);
                }

                $nowSlots = $this->availability->timeslots($branchId, $dateStr, $party);
                if (! in_array($timeStr, $nowSlots, true)) {
                    $payload = app(AppointmentScreen::class)->build($session, $locale, $flowToken);
                    $payload['confirmation_message'] = $this->i18n->tr('slot_taken', $locale);

                    return $this->frame($req, 'APPOINTMENT', $payload);
                }

                // Re-hold quickly for safety (5 min)
                $slotKey = $this->holds->hold(
                    $branchId, $dateStr, $timeStr, $party, (string) ($session?->phone ?? 'unknown'), 300
                );
                $hold = $slotKey ? $this->holds->findActive($slotKey) : null;
                if (! $hold) {
                    $payload = app(AppointmentScreen::class)->build($session, $locale, $flowToken);
                    $payload['confirmation_message'] = $this->i18n->tr('hold_expired', $locale);

                    return $this->frame($req, 'APPOINTMENT', $payload);
                }
            }

            // 2.c) Build attributes (allow SUMMARY edits to overwrite)
            $name = (string) ($req->data['name'] ?? ($c['name'] ?? ''));
            $phone = (string) ($req->data['phone'] ?? ($c['phone'] ?? ($session?->phone ?? '')));
            $email = (string) ($req->data['email'] ?? ($c['email'] ?? ''));
            $notes = (string) ($req->data['notes'] ?? ($c['notes'] ?? ''));

            // Persist these back into ctx/session/contact
            $patch = compact('name', 'phone', 'email', 'notes');
            $this->ctx->put($flowToken, $patch);
            if ($session) {
                $ses = (array) ($session->context ?? []);
                $session->update(['context' => array_replace($ses, $patch)]);
            }
            if ($session && $session->phone) {
                WhatsappContact::updateOrCreate(
                    ['msisdn' => $session->phone],
                    [
                        'name' => $name ?: null,
                        'email' => $email ?: null,
                        'locale' => $session->locale ?: 'en',
                        'last_seen_at' => now(),
                    ]
                );
            }

            // 2.d) Confirm booking
            $attrs = [
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'notes' => $notes,
                'msisdn' => $session?->phone ?: $phone,
            ];
            if (! empty($c['edit_booking_id'])) {
                $attrs['existing_booking_id'] = (int) $c['edit_booking_id']; // BookingService may update
            }

            $booking = $this->bookings->confirmFromHold($hold, $attrs);

            // Stash for finale sender
            $this->ctx->put($flowToken, [
                'last_booking_id' => (int) $booking->id,
                'last_booking_code' => (string) $booking->booking_code,
            ]);

            Log::info('Flow: CONFIRMATION sending', ['booking_code' => (string) $booking->booking_code]);

            // Flow "CONFIRMATION" screen payload (for NFM reply hook)
            return [
                'version' => $req->version,
                'flow_token' => $req->flowToken,
                'screen' => 'CONFIRMATION',
                'data' => [
                    'extension_message_response' => [
                        'params' => [
                            'flow_token' => $req->flowToken,
                            'booking_code' => (string) $booking->booking_code,
                            // Optional extras
                            'date' => (string) $booking->res_date,
                            'time' => (string) $booking->res_time,
                            'party' => (string) $booking->party_size,
                            'branch_name' => Branch::find($booking->branch_id)?->getTranslation('name', $locale) ?? ('#'.$booking->branch_id),
                        ],
                    ],
                ],
            ];
        }

        // Fallback: rebuild from ctx
        $payload = $this->buildSummaryPayload($c, $locale);

        return $this->frame($req, 'SUMMARY', $payload);
    }

    /** Build SUMMARY payload from ctx (robust to partial ctx), including admin terms. */
    private function buildSummaryPayload(array $c, string $locale): array
    {
        $branchId = (int) ($c['branch_id'] ?? 0);
        $party = (int) ($c['party_size'] ?? 0);
        $dateStr = (string) ($c['res_date'] ?? '');
        $timeStr = (string) ($c['res_time'] ?? '');

        $appointment = $this->formatAppointment([
            'branch' => $branchId ?: '',
            'date' => $dateStr,
            'time' => $timeStr,
        ], $locale);

        $details = $this->formatSummary([
            'name' => (string) ($c['name'] ?? ''),
            'party_size' => (string) ($party ?: ''),
            'phone' => (string) ($c['phone'] ?? ''),
            'email' => (string) ($c['email'] ?? ''),
            'notes' => (string) ($c['notes'] ?? ''),
        ], $locale);

        // ⬇️ NEW: terms pulled from DB (per-branch > global)
        $terms = ReservationTerm::forBranch($branchId);

        return [
            'appointment' => $appointment,
            'details' => $details,

            // raw fields for SUMMARY bindings
            'branch' => (string) ($branchId ?: ''),
            'party_size' => (string) ($party ?: ''),
            'date' => (string) $dateStr,
            'time' => (string) $timeStr,
            'name' => (string) ($c['name'] ?? ''),
            'phone' => (string) ($c['phone'] ?? ''),
            'email' => (string) ($c['email'] ?? ''),
            'notes' => (string) ($c['notes'] ?? ''),
            'slot_key' => (string) ($c['slot_key'] ?? ''),

            // admin-managed terms
            'terms_label' => $terms?->label($locale) ?? ($locale === 'ar' ? 'أوافق على شروط الحجز' : 'I agree to the reservation terms'),
            'terms_text' => $terms?->text($locale) ?? '',
            'terms_required' => (bool) ($terms?->terms_required ?? false),
            'terms_error' => '',
        ];
    }

    public function formatAppointment(array $d, string $locale): string
    {
        $date = (string) ($d['date'] ?? '');
        $time = (string) ($d['time'] ?? '');
        $br = (string) ($d['branch'] ?? '');

        $name = $br !== '' ? (Branch::find($br)?->getTranslation('name', $locale) ?? "#$br") : '#?';

        if ($locale === 'ar') {
            return "بارفريز {$name} — {$date} الساعة ".$this->i18n->humanTime($time, 'ar');
        }

        return "Barfres {$name} — {$date} at ".$this->i18n->humanTime($time, 'en');
    }

    public function formatSummary(array $d, string $locale): string
    {
        $name = (string) ($d['name'] ?? '');
        $size = (string) ($d['party_size'] ?? '');
        $phone = (string) ($d['phone'] ?? '');
        $email = (string) ($d['email'] ?? '');
        $notes = (string) ($d['notes'] ?? '');

        if ($locale === 'ar') {
            return "الاسم: {$name}\nعدد الأشخاص: {$size}\nالهاتف: {$phone}\nالبريد الإلكتروني: {$email}\nملاحظات: {$notes}";
        }

        return "Name: {$name}\nParty size: {$size}\nPhone: {$phone}\nEmail: {$email}\nNotes: {$notes}";
    }

    private function frame(FlowRequest $req, string $screen, array|object $data): array
    {
        return [
            'version' => $req->version,
            'flow_token' => $req->flowToken,
            'screen' => $screen,
            'data' => is_array($data) ? $data : (array) $data,
        ];
    }
}
