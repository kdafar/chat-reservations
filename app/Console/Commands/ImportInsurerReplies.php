<?php

namespace App\Console\Commands;

use App\Services\Insurance\InsurerReplyImporter;
use Illuminate\Console\Command;

/**
 * Fetch insurer replies and file them against the statements they answer.
 * Reads the configured IMAP mailbox, or storage/app/insurance-replies/inbox
 * when none is configured.
 */
class ImportInsurerReplies extends Command
{
    protected $signature = 'insurance:import-replies';

    protected $description = 'Import insurer replies to follow-up statements and match them to the statement they answer';

    public function handle(InsurerReplyImporter $importer): int
    {
        try {
            $stats = $importer->run();
        } catch (\Throwable $e) {
            $this->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Source %s — imported %d (matched %d, unmatched %d), skipped %d.',
            $stats['source'], $stats['imported'], $stats['matched'], $stats['unmatched'], $stats['skipped'],
        ));

        return self::SUCCESS;
    }
}
