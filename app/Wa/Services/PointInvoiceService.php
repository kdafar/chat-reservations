<?php

namespace App\Wa\Services;

use App\Wa\Hub\Models\PointPurchase;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PointInvoiceService
{
    public function makeInvoiceNumber(PointPurchase $purchase): string
    {
        // INV-20260102-000010
        $date = now()->format('Ymd');
        $seq = str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT);

        return "INV-{$date}-{$seq}";
    }

    /**
     * Generates invoice PDF, stores it on public disk, returns paths + url.
     */
    public function generatePdf(PointPurchase $purchase, array $opts = []): array
    {
        $locale = $opts['locale'] ?? 'en';

        $invoiceNo = $purchase->invoice_number ?: $this->makeInvoiceNumber($purchase);

        $user = $purchase->user;
        $package = $purchase->pointPackage;

        $pdf = Pdf::loadView('pdf.point-invoice', [
            'locale' => $locale,
            'invoiceNo' => $invoiceNo,
            'date' => ($purchase->created_at ?? now())->format('Y-m-d H:i'),
            'statusLabel' => (string) $purchase->status,

            // Company header
            'companyName' => $opts['company_name'] ?? config('app.name', 'Majestic'),
            'companyLine1' => $opts['company_line1'] ?? 'Kuwait',
            'companyLine2' => $opts['company_line2'] ?? '',

            // Customer
            'customerName' => $user?->name ?: 'Customer',
            'customerPhone' => $user?->phone_number ?: '',
            'customerEmail' => $user?->email ?: null,

            // Payment
            'gatewayLabel' => $purchase->payment_gateway ?: 'N/A',
            'transactionId' => $purchase->transaction_id ?: null,

            // Package
            'packageName' => $package?->name ?: 'Point Package',
            'packageDesc' => $package?->description ?: null,
            'points' => (int) $purchase->points_purchased,
            'amount' => (float) $purchase->amount_paid,
            'currency' => (string) ($purchase->currency ?: 'KWD'),
        ])->setPaper('a4');

        $path = "invoices/points/{$invoiceNo}.pdf";

        Storage::disk('public')->put($path, $pdf->output());

        return [
            'invoice_number' => $invoiceNo,
            'pdf_path' => $path,
            'pdf_url' => Storage::disk('public')->url($path),
        ];
    }
}
