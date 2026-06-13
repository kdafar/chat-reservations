<?php

namespace App\Console\Commands;

use App\Mail\VendorPaymentDueMail;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\User;
use App\Services\WhatsAppApiServiceFactory;
use App\Services\WhatsAppSender;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Daily reminder for vendor payments coming due / overdue on purchase orders
 * (net 30/60/90 credit terms). Notifies clinic admins via in-app notification,
 * email, and WhatsApp (each channel independently switchable in config).
 *
 * Deduped to at most one nudge per PO per day; keeps nudging from
 * `alert_days_before` the due date until the balance is settled.
 */
class ClinicVendorPaymentReminders extends Command
{
    protected $signature = 'clinic:vendor-payment-reminders
        {--force : Run even if clinic.vendor_payment_reminders.enabled is false}
        {--dry-run : Log what would be sent without notifying}';

    protected $description = 'Remind admins when vendor payments are due/overdue (net 30/60/90 terms).';

    public function handle(WhatsAppApiServiceFactory $apiFactory): int
    {
        $cfg = config('clinic.vendor_payment_reminders');

        if (! ($cfg['enabled'] ?? false) && ! $this->option('force')) {
            $this->warn('Vendor payment reminders are disabled (config: clinic.vendor_payment_reminders.enabled).');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run') || ($cfg['dry_run'] ?? false);
        $alertBefore = (int) ($cfg['alert_days_before'] ?? 5);
        $cutoff = now()->startOfDay()->addDays($alertBefore)->toDateString();

        // Candidates: have a due date within the alert window and not already
        // reminded today. Outstanding>0 is checked in PHP (it's computed).
        $orders = PurchaseOrder::query()
            ->withoutGlobalScopes()
            ->whereNotNull('payment_due_date')
            ->whereDate('payment_due_date', '<=', $cutoff)
            ->where(fn ($q) => $q->whereNull('last_payment_reminder_at')
                ->orWhereDate('last_payment_reminder_at', '<', now()->toDateString()))
            ->with(['vendor', 'branch'])
            ->get()
            ->filter(fn (PurchaseOrder $po) => $po->outstanding() > 0.0001);

        $this->info("Vendor payment reminders — {$orders->count()} PO(s) due/overdue. dry_run=".($dryRun ? 'yes' : 'no'));
        if ($orders->isEmpty()) {
            return self::SUCCESS;
        }

        $sender = ($dryRun || ! ($cfg['whatsapp'] ?? false)) ? null : new WhatsAppSender($apiFactory->make());
        $notified = 0;

        foreach ($orders as $po) {
            $days = $po->daysUntilDue();          // negative = overdue
            $overdue = $days !== null && $days < 0;
            $payload = [
                'id' => $po->id,
                'code' => $po->code,
                'vendor' => $po->vendor?->name ?? 'Vendor',
                'outstanding' => number_format($po->outstanding(), 3),
                'due_date' => optional($po->payment_due_date)->toDateString(),
                'days' => (int) ($days ?? 0),
                'overdue' => $overdue,
                'url' => rtrim((string) config('app.url'), '/')."/admin/v2/purchase-orders/{$po->id}",
            ];

            $admins = $this->adminsForPartner($po->branch?->partner_id);
            if ($admins->isEmpty()) {
                $this->line("  PO {$po->code}: no admin recipients — skipped");
                continue;
            }

            $word = $overdue ? "OVERDUE by ".abs($payload['days'])."d" : "due in {$payload['days']}d";
            $this->line("  PO {$po->code} ({$payload['vendor']}, {$payload['outstanding']} KWD, {$word}) → {$admins->count()} admin(s)");

            if ($dryRun) {
                $notified++;
                continue;
            }

            // 1) In-app
            if ($cfg['in_app'] ?? true) {
                $this->sendInApp($admins, $payload);
            }
            // 2) Email
            if ($cfg['email'] ?? true) {
                foreach ($admins as $u) {
                    if (! $u->email) {
                        continue;
                    }
                    try {
                        Mail::to($u->email)->send(new VendorPaymentDueMail($payload));
                    } catch (\Throwable $e) {
                        Log::warning('[vendor-payment-reminders] email failed', ['user' => $u->id, 'msg' => $e->getMessage()]);
                    }
                }
            }
            // 3) WhatsApp (template, gated; off by default)
            if (($cfg['whatsapp'] ?? false) && $sender) {
                $tpl = (string) ($cfg['whatsapp_template'] ?? 'vendor_payment_due');
                $lang = (string) ($cfg['whatsapp_template_lang'] ?? 'en');
                foreach ($admins as $u) {
                    if (! $u->phone) {
                        continue;
                    }
                    try {
                        $sender->sendTemplate($u->phone, $tpl, $lang, [
                            $payload['vendor'], $payload['code'], $payload['outstanding'], $payload['due_date'],
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('[vendor-payment-reminders] whatsapp failed', ['user' => $u->id, 'msg' => $e->getMessage()]);
                    }
                }
            }

            $po->forceFill(['last_payment_reminder_at' => now()])->save();
            $notified++;
        }

        $this->info("Done. notified={$notified}");

        return self::SUCCESS;
    }

    /** Admin recipients for a clinic: the partner's clinic_admins + global admins. */
    protected function adminsForPartner(?int $partnerId)
    {
        // Only reference roles that actually exist (Spatie throws otherwise).
        $existing = \Spatie\Permission\Models\Role::query()
            ->where('guard_name', 'web')->whereIn('name', ['admin', 'super_admin', 'clinic_admin'])
            ->pluck('name');

        $recipients = collect();

        foreach (['admin', 'super_admin'] as $r) {
            if ($existing->contains($r)) {
                $recipients = $recipients->merge(User::query()->role($r)->get());
            }
        }

        if ($partnerId && $existing->contains('clinic_admin')) {
            $recipients = $recipients->merge(
                User::query()->role('clinic_admin')
                    ->whereHas('partners', fn ($q) => $q->where('partners.id', $partnerId))->get()
            );
        }

        return $recipients->unique('id')->values();
    }

    /** Insert a Filament database notification for each admin. */
    protected function sendInApp($admins, array $po): void
    {
        $message = FilamentNotification::make()
            ->title(($po['overdue'] ? 'Vendor payment overdue' : 'Vendor payment due soon').": {$po['code']}")
            ->body("{$po['vendor']} — {$po['outstanding']} KWD ".($po['overdue']
                ? 'overdue by '.abs($po['days']).' day(s)'
                : 'due in '.$po['days'].' day(s)')." (due {$po['due_date']}).")
            ->icon('heroicon-o-banknotes')
            ->iconColor($po['overdue'] ? 'danger' : 'warning')
            ->actions([
                Action::make('view')->label('Open PO')->url($po['url'])->markAsRead(),
            ])
            ->getDatabaseMessage();

        $now = now();
        $rows = $admins->map(fn (User $u) => [
            'id' => (string) Str::uuid(),
            'type' => FilamentDatabaseNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $u->id,
            'data' => json_encode($message),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if ($rows) {
            DB::table('notifications')->insert($rows);
        }
    }
}
