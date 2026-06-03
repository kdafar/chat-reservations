<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\WhatsAppApiServiceFactory;
use App\Services\WhatsAppSender;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendAppointmentReminders extends Command
{
    protected $signature = 'clinic:send-appointment-reminders
                            {--force : Bypass the clinic.reminders.enabled flag (manual run)}
                            {--dry-run : Log what would be sent without calling the WhatsApp API}
                            {--date= : Override target date (YYYY-MM-DD); defaults to today + lead_hours}';

    protected $description = 'Sends WhatsApp reminders for confirmed appointments scheduled within the configured lead window. Disabled by default — see config/clinic.php (reminders.enabled).';

    public function handle(WhatsAppApiServiceFactory $apiFactory): int
    {
        $enabled = (bool) config('clinic.reminders.enabled');
        $force = (bool) $this->option('force');

        if (! $enabled && ! $force) {
            $this->warn('Appointment reminders are disabled (config: clinic.reminders.enabled). Pass --force to run manually.');
            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run') || (bool) config('clinic.reminders.dry_run');
        $leadHours = (int) config('clinic.reminders.lead_hours', 24);
        $template = (string) config('clinic.reminders.template');
        $lang = (string) config('clinic.reminders.template_lang', 'en');

        $target = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : now()->addHours($leadHours)->toDateString();

        $this->info("Reminder window: bookings on {$target} (lead {$leadHours}h). dry_run=".($dryRun ? 'yes' : 'no'));

        $bookings = Booking::query()
            ->with(['patient', 'doctor', 'branch'])
            ->whereDate('res_date', $target)
            ->where('status', Booking::S_CONFIRMED)
            ->whereNull('checked_in_at')
            ->whereNull('cancelled_at')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No confirmed bookings to remind. Done.');
            return self::SUCCESS;
        }

        $sender = $dryRun ? null : new WhatsAppSender($apiFactory->make());
        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($bookings as $b) {
            $to = $b->msisdn ?: ($b->patient?->phone ?? null);
            if (! $to) {
                $skipped++;
                $this->line("  skip booking#{$b->id} — no phone");
                continue;
            }

            $time = $b->res_time ?: optional($b->res_start)->format('H:i');
            $bodyParams = [
                $b->patient?->name ?? 'Patient',
                $b->doctor?->name ?? '—',
                $target,
                (string) $time,
                $b->branch?->getTranslation('name', app()->getLocale(), true) ?? '',
            ];

            if ($dryRun) {
                $this->line("  would send -> {$to} | template={$template} | params=".json_encode($bodyParams));
                $sent++;
                continue;
            }

            $ok = $sender->sendTemplate($to, $template, $lang, $bodyParams);
            if ($ok) {
                $sent++;
                $this->line("  sent -> {$to} (booking#{$b->id})");
            } else {
                $failed++;
                Log::warning('Appointment reminder failed', ['booking_id' => $b->id, 'to' => $to]);
                $this->line("  FAIL -> {$to} (booking#{$b->id})");
            }
        }

        $this->info("Done. sent={$sent} skipped={$skipped} failed={$failed} total={$bookings->count()}");
        return self::SUCCESS;
    }
}
