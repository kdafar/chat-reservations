<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Statement of unpaid claims sent to an insurer from the collections board.
 * One mail per insurer, listing every selected claim of theirs.
 *
 * This is correspondence between two companies, so it is laid out as a
 * letterhead statement — clinic identity, a reference, an addressed party, a
 * priced table and a signature — rather than a notification. The template is
 * hand-rolled table HTML with inline styles because Outlook ignores most of a
 * stylesheet.
 */
class InsurerFollowUpMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array{name:string, code:?string}  $insurer
     * @param  array<int, array{claim_number:string, patient:?string, submitted:?string, age_days:int, outstanding:float, status:string}>  $claims
     * @param  array{count:int, outstanding:float, oldest_days:int, terms_days:int, overdue:float, aging:array}  $totals
     * @param  array{name:string, license:?string, email:?string, phone:?string, address:?string, website:?string}  $clinic
     * @param  array{name:?string, role:string}  $sender
     */
    public function __construct(
        public array $insurer,
        public array $claims,
        public array $totals,
        public array $clinic,
        public array $sender,
        public string $reference,
        public ?string $note = null,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->totals['count'];
        $noun = $count === 1 ? 'claim' : 'claims';

        return new Envelope(
            // The insurer's inbox should show the clinic, not the app name.
            from: new Address(
                config('mail.from.address'),
                $this->clinic['name'] ?: config('mail.from.name'),
            ),
            replyTo: array_filter([
                $this->clinic['email'] ? new Address($this->clinic['email'], $this->clinic['name']) : null,
            ]),
            subject: $this->clinic['name'].' — Statement of outstanding claims ('
                .$count.' '.$noun.', KWD '.number_format($this->totals['outstanding'], 3).') — Ref '.$this->reference,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.insurer-follow-up', with: [
            'insurer' => $this->insurer,
            'claims' => $this->claims,
            'totals' => $this->totals,
            'clinic' => $this->clinic,
            'sender' => $this->sender,
            'reference' => $this->reference,
            'note' => $this->note,
        ]);
    }
}
