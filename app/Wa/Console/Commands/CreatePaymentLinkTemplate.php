<?php

namespace App\Wa\Console\Commands;

use App\Wa\Services\WhatsApp\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Registers (and submits for review) the clinic payment-link template on Meta.
 *
 * This is a UTILITY, body-only template — the MyFatoorah link rides in the
 * body as a variable, so WhatsApp turns it into a tappable link and we stay
 * agnostic to the gateway domain (sandbox vs prod). UTILITY + a clearly
 * transactional body is the fastest path through Meta review.
 *
 * Variables (positional):
 *   {{1}} patient name   {{2}} clinic / branch   {{3}} appointment
 *   {{4}} amount due      {{5}} secure payment link
 *
 * Once Meta approves it, VisitConsoleController::sendPaymentLinkWhatsApp sends
 * it instead of a plain text message — which is what lets the payment link go
 * out even when the patient is OUTSIDE the 24-hour customer-service window.
 */
class CreatePaymentLinkTemplate extends Command
{
    protected $signature = 'wa:create-payment-template
                            {--name= : Override the template name (defaults to config services.whatsapp.templates.payment_link)}
                            {--lang=both : Which language(s) to submit: en, ar, or both}';

    protected $description = 'Submit the clinic payment-link UTILITY template (en/ar) to Meta for review.';

    public function handle(WhatsAppService $wa): int
    {
        $name = (string) ($this->option('name') ?: config('services.whatsapp.templates.payment_link', 'clinic_payment_link'));
        $lang = strtolower((string) $this->option('lang'));

        $langs = match ($lang) {
            'en' => ['en'],
            'ar' => ['ar'],
            default => ['en', 'ar'],
        };

        $this->info("Submitting UTILITY template '{$name}' to Meta for: ".implode(', ', $langs));

        $submitted = 0;

        foreach ($langs as $code) {
            $data = $this->payload($name, $code);

            try {
                $resp = $wa->createTemplateOnMeta($data);
                $this->info(" -> [{$code}] submitted. Meta id: ".($resp['id'] ?? 'n/a').', status: '.($resp['status'] ?? 'PENDING'));
                $submitted++;
            } catch (\Throwable $e) {
                // Most common non-fatal case: this language version already exists.
                $this->warn(" -> [{$code}] skipped/failed: ".$e->getMessage());
            }
        }

        if ($submitted > 0) {
            $this->line('');
            $this->info('Pulling latest template statuses into the local table...');
            try {
                $count = $wa->syncTemplatesFromMeta();
                $this->info(" -> synced {$count} templates from Meta.");
            } catch (\Throwable $e) {
                $this->warn(' -> sync failed (non-fatal): '.$e->getMessage());
            }
        }

        $this->line('');
        $this->info("Done. Review usually completes within minutes to a few hours.");
        $this->line("Re-run sync any time to refresh approval status, then the payment-link button will use the template automatically.");

        return self::SUCCESS;
    }

    /**
     * Build the createTemplateOnMeta() payload for one language. Keep the copy
     * tight and unmistakably transactional so review is fast.
     */
    private function payload(string $name, string $code): array
    {
        if ($code === 'ar') {
            $body = "مرحباً {{1}}، هذا طلب دفع آمن من {{2}}.\n\n"
                ."الموعد: {{3}}\n"
                ."المبلغ المطلوب: {{4}}\n\n"
                ."يمكنك إتمام الدفع بأمان عبر الرابط التالي:\n{{5}}\n\n"
                ."إذا كنت قد دفعت مسبقاً، يُرجى تجاهل هذه الرسالة. شكراً لك.";

            $examples = [
                'سارة',
                'عيادة جلو للتجميل',
                'الخميس 12 يونيو، الساعة 3:30 مساءً',
                '12.500 د.ك',
                'https://demo.myfatoorah.com/KWT/ie/02071234567',
            ];
        } else {
            $body = "Hello {{1}}, this is a secure payment request from {{2}}.\n\n"
                ."Appointment: {{3}}\n"
                ."Amount due: {{4}}\n\n"
                ."You can complete your payment safely using the link below:\n{{5}}\n\n"
                ."If you have already paid, please ignore this message. Thank you.";

            $examples = [
                'Sara',
                'Glow Beauty Clinic',
                'Thu, Jun 12 at 3:30 PM',
                '12.500 KWD',
                'https://demo.myfatoorah.com/KWT/ie/02071234567',
            ];
        }

        return [
            'name' => $name,
            'category' => 'UTILITY',
            'language' => $code,
            'header_type' => 'NONE',
            'body_text' => $body,
            'body_examples' => $examples,
            'footer_text' => null,
            'buttons_data' => [],
        ];
    }
}
