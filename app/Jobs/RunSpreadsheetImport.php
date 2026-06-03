<?php

namespace App\Jobs;

use App\Imports\V2\ImportRegistry;
use App\Imports\V2\SpreadsheetReader;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Background import for large spreadsheets. Re-reads the stored upload, runs the
 * importer (which logs the activity), notifies the user via the database
 * notification bell, then deletes the temp file.
 *
 * Note: backgrounds only when QUEUE_CONNECTION is a real driver (database/redis)
 * with a worker running. Under the default `sync` driver it runs inline.
 */
class RunSpreadsheetImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Allow long-running imports. */
    public int $timeout = 1800;

    public function __construct(
        public string $type,
        public string $filePath,   // path on the 'local' disk
        public string $mode,
        public ?int $userId,
    ) {}

    public function handle(): void
    {
        $importer = ImportRegistry::resolve($this->type);
        if (! $importer) {
            Storage::disk('local')->delete($this->filePath);

            return;
        }

        $user = $this->userId ? User::find($this->userId) : null;

        try {
            $rows = SpreadsheetReader::rows(Storage::disk('local')->path($this->filePath));
            $result = $importer->import($rows, $this->mode, $user); // logs activity
            $this->notify($user, $importer->title(), $result, true);
        } catch (\Throwable $e) {
            $this->notify($user, $importer->title(), null, false, $e->getMessage());
        } finally {
            Storage::disk('local')->delete($this->filePath);
        }
    }

    /**
     * Best-effort completion notification. The import has already finished by
     * the time this runs, so a notification failure (e.g. no tenant context)
     * must never throw — that would fail the job and re-run the import.
     */
    protected function notify(?User $user, string $title, ?array $result, bool $ok, ?string $error = null): void
    {
        if (! $user) {
            return;
        }

        try {
            $notification = Notification::make()->icon('heroicon-o-table-cells');

            if ($ok) {
                $body = "{$result['created']} new, {$result['updated']} updated"
                    .($result['failed'] ? ", {$result['failed']} failed." : '.');
                $notification->title("Import complete — {$title}")->body($body)->success();
            } else {
                $notification->title("Import failed — {$title}")->body($error ?? 'Unknown error')->danger();
            }

            $notification->sendToDatabase($user);
        } catch (\Throwable) {
            // Notification is best-effort; never break the (already-done) import.
        }
    }
}
