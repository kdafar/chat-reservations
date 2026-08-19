<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Appointment reminders. The command itself bails out unless
// clinic.reminders.enabled is true, so the schedule is safe to leave
// registered even while disabled (dev / staging). When enabling in
// production, set CLINIC_REMINDERS_ENABLED=true in .env.
Schedule::command('clinic:send-appointment-reminders')
    ->dailyAt('18:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// Inpatient bed-day charges. Runs just after midnight so yesterday is
// fully closed. Idempotent — safe if the cron fires twice on the same day.
Schedule::command('clinic:accrue-admission-charges')
    ->dailyAt('00:15')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// Monthly straight-line depreciation + prepayment amortization. Run near
// month-end (the commands clamp to the current month and are idempotent per
// asset/schedule-month, so an extra fire is harmless).
Schedule::command('accounting:run-depreciation')
    ->monthlyOn(28, '01:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('accounting:amortize-prepayments')
    ->monthlyOn(28, '01:10')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer();

// Insurer replies to follow-up statements. Off until
// CLINIC_INSURANCE_REPLIES_POLL_ENABLED=true, because polling a mailbox nobody
// has configured just logs failures every 15 minutes. The board's "Check for
// replies" button runs the same import on demand meanwhile.
Schedule::command('insurance:import-replies')
    ->everyFifteenMinutes()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping()
    ->onOneServer()
    ->when(fn () => (bool) config('clinic.insurance_replies.poll_enabled', false));
