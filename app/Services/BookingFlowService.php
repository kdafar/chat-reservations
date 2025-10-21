<?php

namespace App\Services;

use App\Models\WhatsappSession;
use Illuminate\Support\Facades\Log;

class BookingFlowService
{
    public const STATES = [
        'INVITE','SELECT_BRANCH','PARTY_SIZE','DATE_PICK','TIME_PICK','REVIEW','CONFIRMED'
    ];

    public function __construct(
        protected WhatsAppApiServiceFactory $waFactory,
        protected \App\Services\AvailabilityService $availability,
        protected \App\Services\HoldService $holds,
        protected \App\Services\BookingService $bookings,
    ) {}

    public function handle(array $payload): void
    {
        $value   = $payload['entry'][0]['changes'][0]['value'] ?? [];
        $msg     = $value['messages'][0] ?? null;
        $from    = $msg['from'] ?? null;
        if (!$from || !$msg) return;

        // get or create chat session
        $session = WhatsappSession::firstOrCreate(['phone' => $from], [
            'status' => 'active', 'locale' => 'en', 'context' => [],
        ]);
        $ctx   = (array) ($session->context ?? []);
        $state = $ctx['__state'] ?? 'SELECT_BRANCH'; // start here for single-restaurant

        // normalize incoming text/interactive
        [$kind, $text, $payloadId] = $this->extractInput($msg);

        // route by state
        match ($state) {
            'SELECT_BRANCH' => $this->onSelectBranch($session, $ctx, $kind, $text, $payloadId),
            'PARTY_SIZE'    => $this->onPartySize($session, $ctx, $kind, $text, $payloadId),
            'DATE_PICK'     => $this->onDatePick($session, $ctx, $kind, $text, $payloadId),
            'TIME_PICK'     => $this->onTimePick($session, $ctx, $kind, $text, $payloadId),
            'REVIEW'        => $this->onReview($session, $ctx, $kind, $text, $payloadId),
            default         => $this->askSelectBranch($session),
        };
    }

    /* ----------------- STATE HANDLERS ----------------- */

    protected function onSelectBranch($session, array $ctx, string $kind, ?string $text, ?string $payloadId): void
    {
        if (!$payloadId) { $this->askSelectBranch($session); return; }
        $ctx['branch_id'] = (int) $payloadId;
        $ctx['__state'] = 'PARTY_SIZE';
        $this->saveCtx($session, $ctx);
        $this->askPartySize($session);
    }

    protected function onPartySize($session, array $ctx, string $kind, ?string $text, ?string $payloadId): void
    {
        $size = $payloadId ? (int)$payloadId : (int)filter_var((string)$text, FILTER_SANITIZE_NUMBER_INT);
        if ($size <= 0 || $size > 12) { $this->askPartySize($session, 'Please choose a valid size (1-12).'); return; }

        $ctx['party_size'] = $size;
        $ctx['__state'] = 'DATE_PICK';
        $this->saveCtx($session, $ctx);
        $this->askDate($session);
    }

    protected function onDatePick($session, array $ctx, string $kind, ?string $text, ?string $payloadId): void
    {
        $date = $payloadId ?: $text;
        if (!$date) { $this->askDate($session); return; }
        $ctx['res_date'] = $date;
        $ctx['__state'] = 'TIME_PICK';
        $this->saveCtx($session, $ctx);
        $this->askTime($session);
    }

    protected function onTimePick($session, array $ctx, string $kind, ?string $text, ?string $payloadId): void
    {
        $time = $payloadId ?: $text;
        if (!$time) { $this->askTime($session); return; }

        $slotKey = "{$ctx['res_date']}@{$time}@{$ctx['party_size']}@{$ctx['branch_id']}";
        if (!$this->holds->create($ctx['branch_id'], $session->phone, $slotKey, 5)) {
            $this->sender()->sendTextMessage($session->phone, "Oops, that time was just taken. Please pick another time.");
            $this->askTime($session);
            return;
        }

        $ctx['res_time'] = $time;
        $ctx['slot_key'] = $slotKey;
        $ctx['__state'] = 'REVIEW';
        $this->saveCtx($session, $ctx);
        $this->askReview($session);
    }

    protected function onReview($session, array $ctx, string $kind, ?string $text, ?string $payloadId): void
    {
        if ($payloadId === 'confirm') {
            $info = $this->bookings->confirmFromHold(
                $ctx['branch_id'], $session->phone, $ctx['slot_key'], $ctx['party_size']
            );

            $this->sender()->sendTemplate(
                $session->phone, config('settings.whatsapp.templates.confirmed','barfres_confirmed'),
                $this->lang($session), $this->sender()->makeTemplateVars([
                    'date' => $info['date_fmt'], 'time' => $info['time_fmt'],
                    'party_size' => $ctx['party_size'], 'booking_code' => $info['code'],
                    'branch_name' => $info['branch_name'],
                ])
            );

            $ctx['__state'] = 'CONFIRMED';
            $this->saveCtx($session, $ctx);
            return;
        }

        if ($payloadId === 'change') {
            $ctx['__state'] = 'SELECT_BRANCH'; // or jump to which step they want to change
            $this->saveCtx($session, $ctx);
            $this->askSelectBranch($session);
            return;
        }

        $this->askReview($session);
    }

    /* ----------------- PROMPTS ----------------- */

    protected function askSelectBranch($session): void
    {
        $branches = \App\Models\Branch::query()->where('is_available', true)->orderBy('name')->get(['id','name','address']);
        $rows = $branches->map(fn($b)=>['id'=>(string)$b->id,'label'=>$b->name,'desc'=>$b->address])->all();
        $this->sender()->sendListMessage($session->phone, "Choose your branch", "Please select a branch:", $rows);
        $this->bumpState($session, 'SELECT_BRANCH');
    }

    protected function askPartySize($session, string $error = null): void
    {
        $msg = $error ?: "Great! What's your party size?";
        $this->sender()->sendButtons($session->phone, $msg, [
            ['id'=>'2','label'=>'2'], ['id'=>'4','label'=>'4'], ['id'=>'6','label'=>'6'], // up to 3; others can type a number
        ]);
        $this->bumpState($session, 'PARTY_SIZE');
    }

    protected function askDate($session): void
    {
        $ctx = (array)$session->context; $branchId=$ctx['branch_id']; $size=(int)$ctx['party_size'];
        $dates = $this->availability->nextDates($branchId, 5, $size); // returns ['2025-11-03'=>'Mon 3 Nov', ...]
        $rows = collect($dates)->map(fn($label,$value)=>['id'=>$value,'label'=>$label])->values()->all();
        $this->sender()->sendListMessage($session->phone, "Pick a date", "Select your reservation date:", $rows);
        $this->bumpState($session, 'DATE_PICK');
    }

    protected function askTime($session): void
    {
        $ctx = (array)$session->context;
        $slots = $this->availability->timesFor($ctx['branch_id'], $ctx['res_date'], (int)$ctx['party_size']); // [['value'=>'19:30','label'=>'7:30 PM']]
        if (empty($slots)) {
            $this->sender()->sendTextMessage($session->phone, "No slots for that date. Please select another date.");
            $this->askDate($session); return;
        }
        $rows = collect($slots)->take(10)->map(fn($s)=>['id'=>$s['value'],'label'=>$s['label']])->values()->all();
        $this->sender()->sendListMessage($session->phone, "Pick a time", "Available times:", $rows);
        $this->bumpState($session, 'TIME_PICK');
    }

    protected function askReview($session): void
    {
        $c = (array)$session->context;
        $summary = "Confirm booking:\nBranch #{$c['branch_id']}\nParty: {$c['party_size']}\nDate: {$c['res_date']}\nTime: {$c['res_time']}";
        $this->sender()->sendButtons($session->phone, $summary, [
            ['id'=>'confirm','label'=>'Confirm & Book'],
            ['id'=>'change','label'=>'Change details'],
        ]);
    }

    /* ----------------- helpers ----------------- */

    protected function extractInput(array $msg): array
    {
        if (isset($msg['interactive']['button_reply'])) {
            return ['button', null, $msg['interactive']['button_reply']['id'] ?? null];
        }
        if (isset($msg['interactive']['list_reply'])) {
            return ['list', null, $msg['interactive']['list_reply']['id'] ?? null];
        }
        $text = $msg['text']['body'] ?? null;
        return ['text', $text, null];
    }

    protected function bumpState($session, string $state): void
    {
        $ctx = (array)$session->context; $ctx['__state']=$state;
        $this->saveCtx($session, $ctx);
    }

    protected function saveCtx($session, array $ctx): void
    {
        $session->update(['context'=>$ctx, 'last_interacted_at'=>now()]);
    }

    protected function sender(): \App\Services\WhatsAppSender
    {
        return new \App\Services\WhatsAppSender($this->waFactory->make());
    }

    protected function lang($session): string
    {
        return ($session->locale === 'ar') ? 'ar' : 'en_US';
    }
}
