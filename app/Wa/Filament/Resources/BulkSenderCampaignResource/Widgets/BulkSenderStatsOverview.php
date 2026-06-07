<?php

namespace App\Wa\Filament\Resources\BulkSenderCampaignResource\Widgets;

use App\Wa\Hub\Models\PromotionalCampaign;
use App\Wa\Services\WhatsApp\WhatsAppService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BulkSenderStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalCampaigns = PromotionalCampaign::count();

        $campaignsWithCounts = PromotionalCampaign::query()
            ->withCount([
                'recipients',
                'recipients as sent_count' => fn ($q) => $q->where('status', 'sent'),
                'recipients as delivered_count' => fn ($q) => $q->where('status', 'delivered'),
                'recipients as read_count' => fn ($q) => $q->where('status', 'read'),
                'recipients as failed_count' => fn ($q) => $q->where('status', 'failed'),
            ])
            ->get();

        $totalRecipients = (int) $campaignsWithCounts->sum('recipients_count');
        $totalSent = (int) $campaignsWithCounts->sum('sent_count');
        $totalDelivered = (int) $campaignsWithCounts->sum('delivered_count');
        $totalRead = (int) $campaignsWithCounts->sum('read_count');
        $totalFailed = (int) $campaignsWithCounts->sum('failed_count');

        $deliveryRate = $totalRecipients > 0
            ? round(($totalDelivered / $totalRecipients) * 100, 1)
            : 0.0;

        /** @var WhatsAppService $wa */
        $wa = app(WhatsAppService::class);
        $health = $wa->getCurrentNumberHealth();

        // -------------------------
        // Health basics
        // -------------------------
        $qualityRaw = strtoupper($health['quality_rating'] ?? 'UNKNOWN');

        // Meta sometimes returns GREEN/YELLOW/RED, and sometimes HIGH/MEDIUM/LOW (or UNKNOWN).
        $qualityLabel = match ($qualityRaw) {
            'GREEN', 'HIGH' => __('High quality'),
            'YELLOW', 'MEDIUM' => __('Medium quality'),
            'RED', 'LOW' => __('Low quality'),
            default => __('Unknown quality'),
        };

        $healthColor = match ($qualityRaw) {
            'GREEN', 'HIGH' => 'success',
            'YELLOW', 'MEDIUM' => 'warning',
            'RED', 'LOW' => 'danger',
            default => 'secondary',
        };

        // -------------------------
        // Tier: prefer enforced manager limit (new canonical field)
        // -------------------------
        $enforcedTierRaw = $health['whatsapp_business_manager_messaging_limit'] ?? null; // e.g. TIER_2K
        $effectiveTierRaw = $health['effective_messaging_limit_tier'] ?? ($health['messaging_limit_tier'] ?? null);

        // Deprecated/phone-level tier (if you still return it separately in health)
        // If you didn't add it, this will be null and no harm.
        $phoneTierRaw = $health['phone_messaging_limit_tier'] ?? null;

        $fmtTier = function (?string $tierRaw): string {
            if (blank($tierRaw)) {
                return __('Tier N/A');
            }

            // TIER_2K / TIER_10K / TIER_1K => "Tier 2K"
            $t = strtoupper($tierRaw);
            $t = str_replace('TIER_', 'Tier ', $t);
            $t = str_replace('_', ' ', $t);

            return $t;
        };

        $tierLabel = $fmtTier($effectiveTierRaw);
        $enforcedLabel = $fmtTier($enforcedTierRaw);
        $phoneTierLabel = $fmtTier($phoneTierRaw);

        $phoneLabel = $health['display_phone_number'] ?? __('Not configured');

        // -------------------------
        // Details (what to show)
        // -------------------------
        $details = [];
        $details[] = $qualityLabel;

        if (! blank($enforcedTierRaw)) {
            $details[] = __('Enforced limit').": {$enforcedLabel}";
        } else {
            $details[] = __('Limit').": {$tierLabel}";
        }

        if (! blank($enforcedTierRaw) && ! blank($phoneTierRaw) && strtoupper($enforcedTierRaw) !== strtoupper($phoneTierRaw)) {
            $details[] = __('API phone tier').": {$phoneTierLabel} (".__('pending sync').')';
        }

        // Name status
        if (! empty($health['name_status'])) {
            $nameStatus = ucwords(strtolower(str_replace('_', ' ', $health['name_status'])));
            $details[] = __('Name').": {$nameStatus}";
        }

        // Throughput (only if not standard)
        if (! empty($health['throughput_level'])) {
            $throughput = ucwords(strtolower($health['throughput_level']));
            if ($throughput !== 'Standard') {
                $details[] = __('Speed').": {$throughput}";
            }
        }

        // OTP / code verification status (avoid panic on EXPIRED when everything is OK)
        $codeRaw = strtoupper($health['code_verification_status'] ?? '');
        $nameOk = strtoupper($health['name_status'] ?? '') === 'APPROVED';
        $cloudOk = strtoupper($health['platform_type'] ?? '') === 'CLOUD_API';

        $showOtp = ! empty($codeRaw)
            && ! in_array($codeRaw, ['VERIFIED', ''], true)
            && ! ($codeRaw === 'EXPIRED' && $nameOk && $cloudOk);

        if ($showOtp) {
            $codeLabel = ucwords(strtolower(str_replace('_', ' ', $codeRaw)));
            $details[] = __('OTP').": {$codeLabel}";
        }

        if ($nameOk && $cloudOk) {
            $details[] = __('Verification').': '.__('Active');
        }

        // Add status marker if health fetch partially failed (optional, but helpful)
        if (($health['status'] ?? 'ok') !== 'ok') {
            $details[] = __('Health').': '.__('Partial');
        }

        $healthDescription = implode(' • ', $details);

        // Use a more meaningful color for the number card if the enforced tier is low and mismatched,
        // otherwise stick to the quality color.
        $numberDescColor = $healthColor;

        if (! blank($enforcedTierRaw) && ! blank($phoneTierRaw) && strtoupper($enforcedTierRaw) !== strtoupper($phoneTierRaw)) {
            // mismatch should stand out but not panic -> warning
            $numberDescColor = 'warning';
        }

        return [
            Stat::make(__('Campaigns'), number_format($totalCampaigns))
                ->description(__('Total WhatsApp campaigns created'))
                ->icon('heroicon-o-rectangle-group'),

            Stat::make(__('Recipients'), number_format($totalRecipients))
                ->description(__('Total recipients across all campaigns'))
                ->icon('heroicon-o-user-group')
                ->color('primary'),

            Stat::make(__('Delivered'), number_format($totalDelivered))
                ->description($deliveryRate.'% '.__('delivery rate overall'))
                ->icon('heroicon-o-paper-airplane')
                ->color($deliveryRate < 70 ? 'warning' : 'success'),

            Stat::make(__('Read'), number_format($totalRead))
                ->description(__('Read receipts (seen)'))
                ->icon('heroicon-o-eye')
                ->color('success'),

            Stat::make(__('Failed'), number_format($totalFailed))
                ->description(__('Failed / limited / undeliverable attempts'))
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),

            Stat::make(__('WhatsApp Number'), $phoneLabel)
                ->description($healthDescription)
                ->icon('heroicon-o-shield-check')
                ->descriptionColor($numberDescColor)
                ->color('gray'),
        ];
    }
}
