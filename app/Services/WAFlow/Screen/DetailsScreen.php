<?php

namespace App\Services\WAFlow\Screen;

use App\Models\Booking;
use App\Models\Branch;
use App\Models\ReservationTerm; // ⬅️ added
use App\Models\WhatsappContact;
use App\Models\WhatsappSession;
use App\Services\AvailabilityService;
use App\Services\HoldService;
use App\Services\WAFlow\FlowCtx;
use App\Services\WAFlow\FlowLocalization;
use App\Services\WAFlow\FlowRequest;
use Illuminate\Support\Facades\Log;

class DetailsScreen
{
    public function __construct(
        private FlowCtx $ctx,                    // DB-backed flow state
        private FlowLocalization $i18n,
        private HoldService $holds,
        private AvailabilityService $availability,
    ) {}

    public function exchange(FlowRequest $req, ?WhatsappSession $session, string $locale): array
    {
        // Merge: DB ctx wins over legacy session ctx
        $flowToken = $req->flowToken;
        $ctxDb = (array) ($this->ctx->all($flowToken) ?? []);
        $ctxSes = (array) ($session?->context ?? []);
        $c = array_replace($ctxSes, $ctxDb);

        // Pull inline values from request (if present) with normalization
        $branchId = $this->pickScalar($req->data['branch'] ?? ($c['branch'] ?? ($c['branch_id'] ?? '')));
        $partyStr = $this->pickScalar($req->data['party_size'] ?? ($c['party_size'] ?? ($c['party'] ?? '0')), '0');
        $dateStr = $this->pickScalar($req->data['date'] ?? ($c['date'] ?? ($c['res_date'] ?? '')));
        $timeStr = $this->pickScalar($req->data['time'] ?? ($c['time'] ?? ($c['res_time'] ?? '')));

        $party = (int) $partyStr;
        $trigger = (string) ($req->data['trigger'] ?? '');

        Log::info('Flow: DETAILS exchange', [
            'trigger' => $trigger ?: null,
            'branch' => $branchId,
            'party' => $party,
            'date' => $dateStr,
            'time' => $timeStr ?: null,
        ]);

        // Initial render (no trigger) → show DETAILS with prefill
        if ($trigger === '') {
            $prefill = $this->prefill($session, $c);
            Log::info('Flow: DETAILS base render (prefill)', $prefill);

            return $this->frame($req, 'DETAILS', $prefill);
        }

        // Back → APPOINTMENT (keep state)
        if (in_array($trigger, ['back', 'back_to_appointment'], true)) {
            Log::info('Flow: DETAILS → APPOINTMENT (back)');
            $payload = app(AppointmentScreen::class)->build($session, $locale, $flowToken);

            return [
                '__nav' => 'APPOINTMENT',
                '__data' => $payload, // router will re-frame
            ];
        }

        // Submit DETAILS → SUMMARY
        if (in_array($trigger, ['details_submitted', 'go_summary', 'continue'], true)) {
            // Guard (after normalization)
            Log::info('Flow: DETAILS normalized', compact('branchId', 'party', 'dateStr', 'timeStr'));

            if ($branchId === '' || $party <= 0 || $dateStr === '' || $timeStr === '') {
                $payload = app(AppointmentScreen::class)->build($session, $locale, $flowToken);
                $payload['confirmation_message'] = $this->i18n->tr('pick_again', $locale);

                return $this->frame($req, 'APPOINTMENT', $payload);
            }

            // Save form fields to ctx (DB + mirror to session)
            $name = trim((string) ($req->data['name'] ?? ($c['name'] ?? '')));
            $phone = trim((string) ($req->data['phone'] ?? ($c['phone'] ?? ($session->phone ?? ''))));
            $email = trim((string) ($req->data['email'] ?? ($c['email'] ?? '')));
            $notes = trim((string) ($req->data['notes'] ?? ($c['notes'] ?? '')));

            $patch = [
                // store synonyms to keep other screens happy
                'branch_id' => (int) $branchId,
                'branch' => (int) $branchId,
                'party_size' => (int) $party,
                'party' => (int) $party,
                'res_date' => $dateStr,
                'date' => $dateStr,
                'res_time' => $timeStr,
                'time' => $timeStr,

                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'notes' => $notes,
            ];

            // Slot hold policy
            $slotKey = (string) ($c['slot_key'] ?? '');
            $needHold = true;

            if (! empty($c['edit_booking_id'])) {
                if ($b = Booking::find((int) $c['edit_booking_id'])) {
                    if (
                        $b->branch_id == (int) $branchId &&
                        $b->party_size == (int) $party &&
                        $b->res_date === $dateStr &&
                        $b->res_time === $timeStr
                    ) {
                        $needHold = false;
                        $slotKey = ''; // unchanged; no active hold required
                    }
                }
            }

            if ($needHold) {
                if ($slotKey && $this->holds->isValid($slotKey)) {
                    // existing hold still valid
                    Log::info('HoldService: existing hold still valid', ['slot_key' => $slotKey]);
                } else {
                    // sanity: confirm requested time is still available
                    $nowSlots = $this->availability->timeslots((int) $branchId, $dateStr, (int) $party);
                    if (! in_array($timeStr, $nowSlots, true)) {
                        $payload = app(AppointmentScreen::class)->build($session, $locale, $flowToken);
                        $payload['confirmation_message'] = $this->i18n->tr('slot_taken', $locale);

                        return $this->frame($req, 'APPOINTMENT', $payload);
                    }

                    // place a fresh 5-min hold
                    $slotKey = $this->holds->hold(
                        (int) $branchId, $dateStr, $timeStr, (int) $party, (string) $phone, 300
                    );

                    Log::info('HoldService: placed', ['slot_key' => $slotKey]);
                }
            }

            if ($slotKey) {
                $patch['slot_key'] = $slotKey;
            } else {
                unset($patch['slot_key']);
            }

            // Persist ctx + mirror to session
            $this->ctx->put($flowToken, $patch);
            if ($session) {
                $session->update(['context' => array_replace($c, $patch)]);
            }

            // Upsert contact
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

            // Prepare SUMMARY data
            $summary = [
                'appointment' => $this->formatAppointment([
                    'branch' => $branchId,
                    'date' => $dateStr,
                    'time' => $timeStr,
                ], $locale),
                'details' => $this->formatSummary([
                    'name' => $name,
                    'party_size' => (string) $party,
                    'phone' => $phone,
                    'email' => $email,
                    'notes' => $notes,
                ], $locale),

                // raw fields for SUMMARY screen bindings
                'branch' => (string) $branchId,
                'party_size' => (string) $party,
                'date' => (string) $dateStr,
                'time' => (string) $timeStr,
                'name' => (string) $name,
                'phone' => (string) $phone,
                'email' => (string) $email,
                'notes' => (string) $notes,
                'slot_key' => (string) ($slotKey ?: ''),
            ];

            // ⬇️ NEW: inject dynamic terms from DB (per-branch override, else global)
            $terms = ReservationTerm::forBranch((int) $branchId);
            $summary['terms_label'] = $terms?->label($locale) ?? ($locale === 'ar' ? 'أوافق على شروط الحجز' : 'I agree to the reservation terms');
            $summary['terms_required'] = (bool) ($terms?->terms_required ?? false);
            $summary['terms_text'] = (string) ($terms?->text($locale) ?? '');
            $summary['terms_error'] = '';

            // IMPORTANT: frame SUMMARY instead of using __nav for DATA_EXCHANGE responses
            return $this->frame($req, 'SUMMARY', $summary);
        }

        // Fallback: show DETAILS with prefill
        $prefill = $this->prefill($session, $c);

        return $this->frame($req, 'DETAILS', $prefill);
    }

    /** Prefill DETAILS from DB ctx + whatsapp_contacts + legacy session. */
    public function prefill(?WhatsappSession $session, array $c = []): array
    {
        $contact = $session && $session->phone
            ? WhatsappContact::firstWhere('msisdn', $session->phone)
            : null;

        $prefill = [
            'name' => (string) ($c['name'] ?? $contact?->name ?? ''),
            'phone' => (string) ($c['phone'] ?? $session?->phone ?? ''),
            'email' => (string) ($c['email'] ?? $contact?->email ?? ''),
            'notes' => (string) ($c['notes'] ?? ''),
            'branch' => (string) ($c['branch'] ?? ($c['branch_id'] ?? '')),
            'party_size' => (string) ($c['party_size'] ?? ($c['party'] ?? '')),
            'date' => (string) ($c['date'] ?? ($c['res_date'] ?? '')),
            'time' => (string) ($c['time'] ?? ($c['res_time'] ?? '')),
        ];

        Log::info('Flow: DETAILS prefill', [
            'has_phone' => $prefill['phone'] !== '',
            'has_name' => $prefill['name'] !== '',
            'branch' => $prefill['branch'],
            'party' => $prefill['party_size'],
            'date' => $prefill['date'],
            'time' => $prefill['time'],
        ]);

        return $prefill;
    }

    public function formatAppointment(array $d, string $locale): string
    {
        $date = (string) ($d['date'] ?? '');
        $time = (string) ($d['time'] ?? '');
        $br = (string) ($d['branch'] ?? '');

        $name = Branch::find($br)?->getTranslation('name', $locale) ?? "#$br";

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

    private function frame(FlowRequest $req, string $screen, array $data): array
    {
        return [
            'version' => $req->version,
            'flow_token' => $req->flowToken,
            'screen' => $screen,
            'data' => $data,
        ];
        // Navigation responses are returned as ['__nav' => 'SCREEN', '__data' => [...]]
    }

    /**
     * Normalize a value that might be string|array|null into a scalar string.
     * Accepts arrays like ['id' => '4'] or ['4', '6', ...] and returns the id/first.
     */
    private function pickScalar(mixed $v, string $default = ''): string
    {
        if (is_array($v)) {
            if (array_key_exists('id', $v)) {
                return (string) $v['id'];
            }
            $first = reset($v);

            return (string) ($first ?? $default);
        }
        if ($v === null) {
            return $default;
        }

        return (string) $v;
    }
}
