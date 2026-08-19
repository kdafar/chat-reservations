<?php

namespace App\Services\Insurance;

use App\Models\Insurance\InsuranceFollowUpEmail;
use App\Models\Insurance\InsuranceFollowUpEmailReply;
use App\Models\Insurance\Insurer;
use App\Services\Insurance\Mailbox\FolderMailboxSource;
use App\Services\Insurance\Mailbox\ImapMailboxSource;
use App\Services\Insurance\Mailbox\MailboxSource;
use App\Services\Insurance\Mailbox\RawMessageParser;
use Illuminate\Support\Facades\Log;

/**
 * Pulls insurer replies out of a mailbox and ties each one to the statement it
 * answers.
 *
 * Matching, in descending order of confidence:
 *   1. our reference (AR-YYYYMMDD-CODE) quoted anywhere in subject or body —
 *      it survives forwards and reply chains, which threading headers do not;
 *   2. In-Reply-To pointing at the Message-ID we recorded when sending;
 *   3. the sender's address against an insurer's claims inbox, attached to
 *      their most recent statement still awaiting a reply.
 *
 * Anything that matches none of those is stored unmatched rather than dropped —
 * a reply nobody can file is still a reply somebody must read. What the reply
 * MEANS is never inferred here: the outcome and any promised date stay a human
 * decision on the statement.
 */
class InsurerReplyImporter
{
    public function __construct(private RawMessageParser $parser) {}

    /** @return array{imported:int, matched:int, unmatched:int, skipped:int, source:string} */
    public function run(?MailboxSource $source = null): array
    {
        $source ??= $this->defaultSource();
        $stats = ['imported' => 0, 'matched' => 0, 'unmatched' => 0, 'skipped' => 0, 'source' => $source->name()];

        foreach ($source->messages() as $message) {
            try {
                // Re-running the import must never duplicate a reply.
                if (InsuranceFollowUpEmailReply::where('message_id', $message['message_id'])->exists()) {
                    $stats['skipped']++;
                    $source->markHandled($message);

                    continue;
                }

                [$statement, $matchedBy] = $this->match($message);

                InsuranceFollowUpEmailReply::create([
                    'followup_email_id' => $statement?->id,
                    'insurer_id' => $statement?->insurer_id ?? $this->insurerByEmail($message['from_email'])?->id,
                    'from_email' => $message['from_email'],
                    'from_name' => $message['from_name'],
                    'subject' => $message['subject'],
                    'message_id' => $message['message_id'],
                    'in_reply_to' => $message['in_reply_to'] ?? null,
                    'received_at' => $message['received_at'] ?? now(),
                    'body_text' => $message['body'],
                    'matched_by' => $matchedBy,
                    'status' => $statement
                        ? InsuranceFollowUpEmailReply::STATUS_MATCHED
                        : InsuranceFollowUpEmailReply::STATUS_UNMATCHED,
                    'source' => $source->name(),
                ]);

                $statement ? $stats['matched']++ : $stats['unmatched']++;
                $stats['imported']++;
                $source->markHandled($message);
            } catch (\Throwable $e) {
                // One malformed message must not stall the whole mailbox.
                report($e);
                Log::warning('[InsurerReplyImporter] message skipped', ['error' => $e->getMessage()]);
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /** @return array{0:?InsuranceFollowUpEmail, 1:?string} */
    private function match(array $message): array
    {
        $haystack = ($message['subject'] ?? '').' '.($message['body'] ?? '');

        if (preg_match('/\bAR-\d{8}-[A-Z0-9]+\b/i', $haystack, $m)) {
            $reference = strtoupper($m[0]);
            $statement = InsuranceFollowUpEmail::query()
                ->where('meta->reference', $reference)
                ->latest('id')->first();
            if ($statement) {
                return [$statement, 'reference'];
            }
        }

        if (! empty($message['in_reply_to'])) {
            $statement = InsuranceFollowUpEmail::query()
                ->where('meta->message_id', $message['in_reply_to'])
                ->latest('id')->first();
            if ($statement) {
                return [$statement, 'thread'];
            }
        }

        if ($insurer = $this->insurerByEmail($message['from_email'] ?? null)) {
            $statement = InsuranceFollowUpEmail::query()
                ->where('insurer_id', $insurer->id)
                ->where('status', InsuranceFollowUpEmail::STATUS_SENT)
                ->whereNull('reply_outcome')
                ->latest('sent_at')->first();
            if ($statement) {
                return [$statement, 'sender'];
            }
        }

        return [null, null];
    }

    private function insurerByEmail(?string $email): ?Insurer
    {
        if (! $email) {
            return null;
        }

        return Insurer::query()->whereRaw('LOWER(contact_email) = ?', [strtolower($email)])->first();
    }

    /** IMAP when it's configured, the drop folder otherwise. */
    public function defaultSource(): MailboxSource
    {
        $imap = (array) config('clinic.insurance_replies.imap', []);

        if (! empty($imap['host']) && ! empty($imap['username'])) {
            return new ImapMailboxSource($this->parser, $imap);
        }

        return new FolderMailboxSource($this->parser);
    }
}
