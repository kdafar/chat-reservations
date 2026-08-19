<?php

namespace App\Services\Insurance;

use App\Models\Insurance\InsuranceFollowUpEmail;
use App\Services\Insurance\Mailbox\FolderMailboxSource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes a realistic insurer reply into the import folder as a .eml.
 *
 * Fakes the delivery only: the file is a genuine RFC-822 message quoting our
 * reference with the original statement below it, so the parser, the matcher
 * and the board all behave exactly as they would with a real reply.
 *
 * Shared by the artisan command and the demo button, so the two can never
 * drift into producing different messages.
 */
class SimulatedReplyFactory
{
    public const TONES = ['promise', 'documents', 'reject'];

    /** @return array{file:string, from:string, reference:string} */
    public function create(InsuranceFollowUpEmail $statement, string $tone = 'promise'): array
    {
        $statement->loadMissing('insurer');

        $reference = $statement->meta['reference'] ?? 'AR-'.now()->format('Ymd').'-REF';
        $payDate = now()->addDays(14);

        $body = match ($tone) {
            'documents' => "Dear Sir/Madam,\n\nThank you for your statement (Ref {$reference}).\n\n"
                ."We require the treating physician's report and the itemised invoice for the claims listed before "
                ."settlement can be processed. Kindly resend them at your earliest convenience.\n\n"
                ."Best regards,\nClaims Department",
            'reject' => "Dear Sir/Madam,\n\nWith reference to your statement {$reference}, the listed claims fall "
                ."outside the member's policy coverage for the period concerned and cannot be reimbursed.\n\n"
                ."Kindly bill the patient directly.\n\nRegards,\nClaims Department",
            default => "Dear Sir/Madam,\n\nThank you for your follow-up (Ref {$reference}).\n\n"
                .'The claims listed have been approved by our medical review team. Payment of KWD '
                .number_format((float) $statement->total_outstanding, 3)." is scheduled for {$payDate->format('d M Y')} "
                ."and the transfer reference will follow by email.\n\nWe apologise for the delay.\n\n"
                ."Best regards,\nClaims Department",
        };

        $from = $statement->to_email;
        $messageId = Str::uuid()->toString().'@'.Str::after($from, '@');
        $ourId = $statement->meta['message_id'] ?? null;

        // array_filter without a callback would drop the blank lines, and a
        // message with no blank line after its headers has no body at all.
        $eml = implode("\r\n", array_filter([
            'Return-Path: <'.$from.'>',
            'From: "'.($statement->insurer?->name ?? 'Claims Department').'" <'.$from.'>',
            'To: <'.config('mail.from.address').'>',
            'Subject: RE: '.$statement->subject,
            'Date: '.now()->toRfc2822String(),
            'Message-ID: <'.$messageId.'>',
            $ourId ? 'In-Reply-To: <'.$ourId.'>' : null,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            quoted_printable_encode($body),
            '',
            // The original statement quoted underneath — the parser has to cut
            // this off, exactly as it would with a real reply.
            'On '.optional($statement->sent_at)->format('d M Y').', '.config('app.name').' wrote:',
            '> '.$statement->subject,
            '> Total outstanding: KWD '.number_format((float) $statement->total_outstanding, 3),
        ], fn ($line) => $line !== null));

        $file = 'reply-'.$statement->id.'-'.now()->format('YmdHis').'.eml';
        Storage::disk('local')->put(FolderMailboxSource::INBOX.'/'.$file, $eml);

        return ['file' => $file, 'from' => $from, 'reference' => $reference];
    }
}
