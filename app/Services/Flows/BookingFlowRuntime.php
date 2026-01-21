<?php

namespace App\Services\Flows;

use App\Models\Branch;
use App\Models\WhatsappSession;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\HoldService;
use Carbon\Carbon;

class BookingFlowRuntime
{
    public function __construct(
        protected AvailabilityService $availability,
        protected HoldService $holds,
        protected BookingService $bookings,
    ) {}

    public function handle(array $in): array
    {
        $trigger = (string) ($in['trigger'] ?? '');
        $msisdn = (string) ($in['phone'] ?? $in['msisdn'] ?? '');
        $session = WhatsappSession::firstWhere('phone', $msisdn);
        $ctx = (array) ($session?->context ?? []);
        $tz = config('app.timezone');

        switch ($trigger) {
            case 'branch_selected':
                $ctx['branch_id'] = (int) ($in['branch'] ?? ($in['single_branch_id'] ?? 0));
                $this->saveCtx($session, $ctx);

                return ['version' => '3.0', 'data' => []];

            case 'party_selected':
                $ctx['party_size'] = (int) ($in['party_size'] ?? 0);
                $this->saveCtx($session, $ctx);

                return ['version' => '3.0', 'data' => $this->dateRules(($ctx['branch_id'] ?? null), ($ctx['party_size'] ?? null))];

            case 'date_selected':
                $ctx['res_date'] = (string) ($in['date'] ?? '');
                $this->saveCtx($session, $ctx);
                [$time, $show] = $this->timeOptions((int) ($ctx['branch_id'] ?? 0), (string) $ctx['res_date'], (int) ($ctx['party_size'] ?? 0));

                return ['version' => '3.0', 'data' => ['time' => $time, 'show_time' => $show]];

            case 'time_selected':
                $branchId = (int) ($in['branch'] ?? ($ctx['branch_id'] ?? 0));
                $size = (int) ($in['party_size'] ?? ($ctx['party_size'] ?? 0));
                $date = (string) ($in['date'] ?? ($ctx['res_date'] ?? ''));
                $time = (string) ($in['time'] ?? '');

                if (! $branchId || ! $size || ! $date || ! $time) {
                    return ['version' => '3.0', 'data' => [], 'errors' => [['message' => 'Missing selection']]];
                }

                $slotKey = "{$date}@{$time}@{$size}@{$branchId}";
                if (! $this->holds->create($branchId, $msisdn, $slotKey, 5)) {
                    // race lost → refresh list
                    [$opts] = $this->timeOptions($branchId, $date, $size);

                    return [
                        'version' => '3.0',
                        'data' => ['time' => $opts, 'show_time' => true],
                        'errors' => [['field' => 'time', 'message' => 'That slot was taken. Pick another.']],
                    ];
                }

                $ctx['branch_id'] = $branchId;
                $ctx['party_size'] = $size;
                $ctx['res_date'] = $date;
                $ctx['res_time'] = $time;
                $ctx['slot_key'] = $slotKey;
                $this->saveCtx($session, $ctx);

                return ['version' => '3.0', 'data' => []];

            case 'details_submitted':
                $ctx['name'] = (string) ($in['name'] ?? '');
                $ctx['phone'] = (string) ($in['phone'] ?? $msisdn);
                $ctx['email'] = (string) ($in['email'] ?? '');
                $ctx['notes'] = (string) ($in['notes'] ?? '');
                $this->saveCtx($session, $ctx);

                $branchName = Branch::find($ctx['branch_id'])?->name ?? ('#'.$ctx['branch_id']);

                $appointment = sprintf(
                    '%s — %s at %s',
                    $branchName,
                    Carbon::parse($ctx['res_date'], $tz)->isoFormat('ddd MMM D, YYYY'),
                    Carbon::parse($ctx['res_time'], $tz)->format('g:i A')
                );

                $details = "Name: {$ctx['name']}\nParty size: {$ctx['party_size']}\nPhone: {$ctx['phone']}";
                if (! empty($ctx['email'])) {
                    $details .= "\nEmail: {$ctx['email']}";
                }
                if (! empty($ctx['notes'])) {
                    $details .= "\nNotes: {$ctx['notes']}";
                }

                return [
                    'version' => '3.0',
                    'data' => [
                        'appointment' => $appointment,
                        'details' => $details,
                        'branch' => (string) $ctx['branch_id'],
                        'party_size' => (string) $ctx['party_size'],
                        'date' => (string) $ctx['res_date'],
                        'time' => (string) $ctx['res_time'],
                        'name' => (string) $ctx['name'],
                        'phone' => (string) $ctx['phone'],
                        'email' => (string) $ctx['email'],
                        'notes' => (string) $ctx['notes'],
                    ],
                ];

            case 'confirm_booking':
                if (empty($in['agree_terms'])) {
                    return ['version' => '3.0', 'errors' => [['field' => 'agree_terms', 'message' => 'Please accept the terms']]];
                }

                $hold = $this->holds->findActive((string) ($ctx['slot_key'] ?? ''));
                if (! $hold) {
                    [$opts] = $this->timeOptions(
                        (int) ($ctx['branch_id'] ?? 0),
                        (string) ($ctx['res_date'] ?? ''),
                        (int) ($ctx['party_size'] ?? 0)
                    );

                    return [
                        'version' => '3.0',
                        'data' => ['time' => $opts, 'show_time' => true],
                        'errors' => [['message' => 'Hold expired. Please pick time again.']],
                    ];
                }

                $this->bookings->confirmFromHold($hold, [
                    'name' => (string) ($ctx['name'] ?? ''),
                    'phone' => (string) ($ctx['phone'] ?? $msisdn),
                    'email' => (string) ($ctx['email'] ?? ''),
                    'notes' => (string) ($ctx['notes'] ?? ''),
                ]);

                // Tell the Flow to navigate to CONFIRMATION
                return [
                    'version' => '3.0',
                    'navigate' => ['name' => 'CONFIRMATION'],
                    'data' => [],
                ];

            default:
                return ['version' => '3.0', 'data' => []];
        }
    }

    protected function dateRules(?int $branchId, ?int $size): array
    {
        $min = now(config('app.timezone'))->toDateString();
        $max = now(config('app.timezone'))->addDays(60)->toDateString();

        return [
            'is_date_enabled' => true,
            'min_date' => $min,
            'max_date' => $max,
            'unavailable_dates' => [],
            'include_days' => ['Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];
    }

    protected function timeOptions(int $branchId, string $dateYmd, int $size): array
    {
        $slots = $this->availability->timesFor($branchId, $dateYmd, $size);
        $opts = collect($slots)->take(10)->map(fn ($s) => [
            'id' => (string) $s['value'],
            'title' => (string) $s['label'],
            'enabled' => true,
            'image' => self::tinyPixel(),
        ])->values()->all();

        return [$opts, ! empty($opts)];
    }

    protected function saveCtx(?WhatsappSession $session, array $ctx): void
    {
        if ($session) {
            $session->update(['context' => $ctx, 'last_interacted_at' => now()]);
        }
    }

    protected static function tinyPixel(): string
    {
        return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO2Z1mQAAAAASUVORK5CYII=';
    }
}
