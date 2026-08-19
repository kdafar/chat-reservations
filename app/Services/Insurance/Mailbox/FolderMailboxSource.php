<?php

namespace App\Services\Insurance\Mailbox;

use Illuminate\Support\Facades\Storage;

/**
 * Reads replies from .eml files on disk (storage/app/insurance-replies/inbox),
 * moving each into processed/ once stored.
 *
 * This is the transport used when no mailbox is configured. It is a genuine
 * source, not a stub: the files it reads are real RFC-822 messages and they go
 * through exactly the same parser and matcher as IMAP would deliver. It also
 * doubles as the fixture path — drop a saved insurer reply in and re-run the
 * import to see how it would have been handled.
 */
class FolderMailboxSource implements MailboxSource
{
    public const INBOX = 'insurance-replies/inbox';

    public const PROCESSED = 'insurance-replies/processed';

    public function __construct(private RawMessageParser $parser) {}

    public function name(): string
    {
        return 'folder';
    }

    public function messages(): iterable
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::INBOX)) {
            $disk->makeDirectory(self::INBOX);

            return;
        }

        foreach ($disk->files(self::INBOX) as $path) {
            if (! str_ends_with(strtolower($path), '.eml')) {
                continue;
            }

            $message = $this->parser->parse($disk->get($path));
            // No Message-ID means no dedupe key; derive a stable one from the file.
            $message['message_id'] = $message['message_id'] ?: 'file-'.sha1($path);
            $message['_path'] = $path;

            yield $message;
        }
    }

    public function markHandled(array $message): void
    {
        $path = $message['_path'] ?? null;
        if (! $path) {
            return;
        }

        $disk = Storage::disk('local');
        $disk->makeDirectory(self::PROCESSED);
        $disk->move($path, self::PROCESSED.'/'.basename($path));
    }
}
