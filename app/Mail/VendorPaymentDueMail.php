<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to clinic admins when a vendor payment is due / overdue on a PO.
 */
class VendorPaymentDueMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{code:string,vendor:string,outstanding:string,due_date:string,days:int,overdue:bool,url:string}  $po
     */
    public function __construct(public array $po) {}

    public function envelope(): Envelope
    {
        $state = $this->po['overdue'] ? 'OVERDUE' : 'due soon';

        return new Envelope(
            subject: "Vendor payment {$state}: {$this->po['vendor']} ({$this->po['code']})",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.vendor-payment-due', with: ['po' => $this->po]);
    }
}
