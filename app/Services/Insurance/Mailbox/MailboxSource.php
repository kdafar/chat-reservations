<?php

namespace App\Services\Insurance\Mailbox;

/**
 * Where inbound insurer replies come from.
 *
 * The importer neither knows nor cares whether a message arrived over IMAP or
 * was dropped into a folder — it receives the same normalised shape either way,
 * so the parsing and matching that matter are exercised identically in a demo
 * and in production.
 *
 * A message is: [
 *   'message_id' => string, 'in_reply_to' => ?string,
 *   'from_email' => ?string, 'from_name' => ?string,
 *   'subject' => ?string, 'received_at' => ?string, 'body' => string,
 * ]
 */
interface MailboxSource
{
    /** @return iterable<int, array<string, mixed>> */
    public function messages(): iterable;

    /** Transport name recorded against each stored reply. */
    public function name(): string;

    /** Called once a message is safely stored, so it isn't fetched again. */
    public function markHandled(array $message): void;
}
