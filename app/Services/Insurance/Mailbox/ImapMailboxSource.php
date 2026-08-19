<?php

namespace App\Services\Insurance\Mailbox;

/**
 * Reads unseen replies from a real mailbox over IMAP, using the ext-imap
 * functions already present on the server (no extra dependency).
 *
 * Configure clinic.insurance_replies.imap.* to switch the importer onto this
 * source. UNTESTED against a live mailbox — there are no credentials for one
 * yet; the parsing and matching it feeds are exercised through
 * FolderMailboxSource. Treat the connection handling here as needing one real
 * run-through before it is relied on.
 */
class ImapMailboxSource implements MailboxSource
{
    /** @var resource|\IMAP\Connection|null */
    private $connection = null;

    private array $handled = [];

    public function __construct(private RawMessageParser $parser, private array $config) {}

    public function name(): string
    {
        return 'imap';
    }

    public function messages(): iterable
    {
        if (! function_exists('imap_open')) {
            throw new \RuntimeException('The imap PHP extension is not available.');
        }

        $mailbox = sprintf(
            '{%s:%d/imap/%s}%s',
            $this->config['host'] ?? 'localhost',
            (int) ($this->config['port'] ?? 993),
            $this->config['encryption'] ?? 'ssl',
            $this->config['folder'] ?? 'INBOX',
        );

        $this->connection = @imap_open($mailbox, (string) ($this->config['username'] ?? ''), (string) ($this->config['password'] ?? ''));
        if (! $this->connection) {
            throw new \RuntimeException('IMAP connection failed: '.imap_last_error());
        }

        try {
            $ids = imap_search($this->connection, 'UNSEEN') ?: [];
            foreach ($ids as $id) {
                // Peek: leave \Seen alone until the reply is safely stored.
                $raw = imap_fetchheader($this->connection, $id, FT_PREFETCHTEXT)
                    .imap_body($this->connection, $id, FT_PEEK);

                $message = $this->parser->parse($raw);
                $message['message_id'] = $message['message_id'] ?: 'imap-'.$id.'-'.sha1($raw);
                $message['_uid'] = $id;

                yield $message;
            }
        } finally {
            foreach ($this->handled as $id) {
                imap_setflag_full($this->connection, (string) $id, '\\Seen');
            }
            imap_close($this->connection);
            $this->connection = null;
        }
    }

    public function markHandled(array $message): void
    {
        if (isset($message['_uid'])) {
            $this->handled[] = $message['_uid'];
        }
    }
}
