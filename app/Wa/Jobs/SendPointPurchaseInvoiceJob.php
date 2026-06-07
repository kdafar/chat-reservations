<?php

namespace App\Wa\Jobs;

use App\Wa\Hub\Models\PointPurchase;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use App\Wa\Services\PointInvoiceService;
use App\Wa\Services\WhatsAppBot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class SendPointPurchaseInvoiceJob implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $purchaseId) {}

    public function handle(PointInvoiceService $invoices, WhatsAppBot $wa): void
    {
        $purchase = PointPurchase::with(['user', 'pointPackage'])->find($this->purchaseId);
        if (! $purchase) {
            return;
        }

        // Only for successful purchases
        if (! in_array((string) $purchase->status, ['paid', 'completed'], true)) {
            return;
        }

        // prevent duplicates
        if ($purchase->invoice_sent_at) {
            return;
        }

        $user = $purchase->user;

        // IMPORTANT: your users table has phone_number + preferred_locale
        $mobile = $user?->phone_number;
        $locale = $user?->preferred_locale ?: 'en';

        if (! $mobile) {
            Log::warning('[PointInvoice] Missing user phone_number', [
                'purchase_id' => $purchase->id,
                'user_id' => $purchase->user_id,
            ]);

            return;
        }

        // Generate PDF if missing
        if (! $purchase->invoice_number || ! $purchase->invoice_pdf_path || ! Storage::disk('public')->exists($purchase->invoice_pdf_path)) {
            $result = $invoices->generatePdf($purchase, [
                'locale' => $locale,
            ]);

            $purchase->invoice_number = $result['invoice_number'];
            $purchase->invoice_pdf_path = $result['pdf_path'];
            $purchase->save();
        }

        $pdfUrl = Storage::disk('public')->url($purchase->invoice_pdf_path);

        // Send to WhatsApp
        $wa->sendPointInvoice(
            $mobile,
            $pdfUrl,
            $purchase->invoice_number,
            $locale
        );

        $purchase->invoice_sent_at = now();
        $purchase->save();
    }
}
