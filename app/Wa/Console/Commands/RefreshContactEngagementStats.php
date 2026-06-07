<?php

namespace App\Wa\Console\Commands;

use App\Wa\Hub\Models\Contact;
use App\Wa\Hub\Models\ContactEngagementStat;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Models\WhatsApp\WaMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Rebuilds ContactEngagementStat from campaign-recipient history plus inbound
 * reply counts. Scheduled hourly; also invoked by the v2 "Refresh engagement"
 * button so both paths share identical logic.
 */
class RefreshContactEngagementStats extends Command
{
    protected $signature = 'wa:contacts:refresh-engagement-stats';

    protected $description = 'Rebuild WhatsApp contact engagement stats (sent/delivered/read/failed/replied).';

    public function handle(): int
    {
        $touched = self::rebuild();
        $this->info("Engagement refreshed for {$touched} contacts.");

        return self::SUCCESS;
    }

    /** Shared logic; returns number of contacts touched. */
    public static function rebuild(): int
    {
        $agg = PromotionalCampaignRecipient::query()
            ->selectRaw('msisdn,
                COUNT(DISTINCT promotional_campaign_id) campaigns_count,
                SUM(status IN ("sent","delivered","read")) sent_count,
                SUM(status IN ("delivered","read")) delivered_count,
                SUM(status = "read") read_count,
                SUM(status IN ("failed","limited","undeliverable","experiment_blocked")) failed_count,
                SUM(status = "pending") pending_count,
                MAX(GREATEST(COALESCE(read_at,0), COALESCE(delivered_at,0), COALESCE(sent_at,0))) last_activity')
            ->groupBy('msisdn')->get()->keyBy('msisdn');

        $p = (new WaMessage)->getConnection()->getTablePrefix();
        $replied = WaMessage::query()
            ->where('wa_messages.direction', 'inbound')
            ->join('wa_contacts', 'wa_messages.contact_id', '=', 'wa_contacts.id')
            ->selectRaw("{$p}wa_contacts.phone as phone, COUNT(*) replied, MAX({$p}wa_messages.created_at) last_replied")
            ->groupBy('wa_contacts.phone')->get()->keyBy('phone');

        $touched = 0;
        Contact::query()->chunkById(500, function ($contacts) use ($agg, $replied, &$touched) {
            foreach ($contacts as $contact) {
                $a = $agg->get($contact->msisdn);
                $rep = $replied->get($contact->msisdn)
                    ?? $replied->get(ltrim((string) $contact->msisdn, '+'))
                    ?? $replied->get('+'.ltrim((string) $contact->msisdn, '+'));
                if (! $a && ! $rep) {
                    continue;
                }
                $last = $a && $a->last_activity && $a->last_activity !== '0' ? $a->last_activity : null;
                $lastReplied = $rep->last_replied ?? null;
                $lastAct = collect([$last, $lastReplied])->filter()->max();
                ContactEngagementStat::updateOrCreate(['contact_id' => $contact->id], [
                    'campaigns_count' => $a->campaigns_count ?? 0,
                    'sent_count' => $a->sent_count ?? 0,
                    'delivered_count' => $a->delivered_count ?? 0,
                    'read_count' => $a->read_count ?? 0,
                    'failed_count' => $a->failed_count ?? 0,
                    'pending_count' => $a->pending_count ?? 0,
                    'replied_count' => $rep->replied ?? 0,
                    'last_replied_at' => $lastReplied,
                    'last_activity_at' => $lastAct ?: null,
                    'is_active' => $lastAct ? Carbon::parse($lastAct)->gt(now()->subDays(30)) : false,
                ]);
                $touched++;
            }
        });

        return $touched;
    }
}
