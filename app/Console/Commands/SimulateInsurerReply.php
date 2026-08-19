<?php

namespace App\Console\Commands;

use App\Models\Insurance\InsuranceFollowUpEmail;
use App\Services\Insurance\SimulatedReplyFactory;
use Illuminate\Console\Command;

/**
 * CLI face of SimulatedReplyFactory — drops a realistic insurer reply into the
 * import folder so the inbound path can be exercised without a live mailbox.
 * The follow-up board has a button that does the same thing when demo mode is
 * on; this is for staging fixtures before a session.
 */
class SimulateInsurerReply extends Command
{
    protected $signature = 'insurance:simulate-reply
        {--statement= : Statement (follow-up email) id to reply to; defaults to the newest awaiting a reply}
        {--tone=promise : promise|documents|reject}';

    protected $description = 'Drop a simulated insurer reply into the import folder (demo/testing aid)';

    public function handle(SimulatedReplyFactory $factory): int
    {
        $statement = $this->option('statement')
            ? InsuranceFollowUpEmail::find($this->option('statement'))
            : InsuranceFollowUpEmail::where('status', InsuranceFollowUpEmail::STATUS_SENT)
                ->whereNull('reply_outcome')->latest('id')->first();

        if (! $statement) {
            $this->error('No sent statement is awaiting a reply. Send one from the follow-up board first.');

            return self::FAILURE;
        }

        $tone = in_array($this->option('tone'), SimulatedReplyFactory::TONES, true)
            ? $this->option('tone')
            : 'promise';

        $result = $factory->create($statement, $tone);

        $this->info("Dropped {$result['file']} — a '{$tone}' reply from {$result['from']} quoting {$result['reference']}.");
        $this->line('Run `php artisan insurance:import-replies` (or press "Check for replies" on the board) to file it.');

        return self::SUCCESS;
    }
}
