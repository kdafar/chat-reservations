<?php

namespace App\Jobs;

use App\Models\BulkInviteCampaign;
use App\Models\BulkInviteCampaignRecipient;
use App\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\SimpleExcel\SimpleExcelReader;

class ImportBulkInviteRecipients implements NotTenantAware, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  string  $storedFilePath  Path returned by FileUpload (public disk or default disk)
     * @param  bool  $hasHeader  If false, expects [0=>phone,1=>name,2=>locale]
     * @param  string  $defaultRegion  Preferred region for parsing local-format numbers (no +)
     * @param  bool  $dedupe  Update existing rows (by campaign + msisdn) instead of inserting duplicates
     * @param  array  $allowedRegions  Whitelist of regions to accept (default: GCC + Egypt)
     * @param  bool  $mobileOnly  Accept only mobile / fixed_line_or_mobile types
     */
    public function __construct(
        public int $campaignId,
        public string $storedFilePath,
        public bool $hasHeader = true,
        public string $defaultRegion = 'KW',
        public bool $dedupe = true,
        public array $allowedRegions = ['KW', 'SA', 'AE', 'QA', 'BH', 'OM', 'EG'],
        public bool $mobileOnly = true,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $campaign = BulkInviteCampaign::findOrFail($this->campaignId);

        $abs = $this->resolvePath($this->storedFilePath);
        if (! $abs || ! is_file($abs)) {
            Log::warning('[ImportRecipients] File not found', ['path' => $this->storedFilePath]);

            return;
        }

        $reader = SimpleExcelReader::create($abs);
        if (! $this->hasHeader) {
            // rows => [0=>phone, 1=>name, 2=>locale]
            $reader->noHeaderRow();
        }

        $added = 0;
        $updated = 0;
        $skippedInvalid = 0;

        $reader->getRows()->each(function (array $row) use ($campaign, &$added, &$updated, &$skippedInvalid) {
            [$msisdn, $name, $locale] = $this->extractRow($row, (string) ($campaign->default_locale ?? 'en'));

            if (! $msisdn) {
                $skippedInvalid++;

                return;
            }

            $attrs = [
                'bulk_invite_campaign_id' => $campaign->id,
                'msisdn' => $msisdn,
            ];

            $vals = [
                'name' => $name,
                'locale' => $locale,
                'status' => 'pending',
                'source' => 'excel',
                'wa_message_id' => null,
                'error_message' => null,
            ];

            if ($this->dedupe) {
                $exists = BulkInviteCampaignRecipient::query()->where($attrs)->exists();
                BulkInviteCampaignRecipient::query()->updateOrCreate($attrs, $vals);
                $exists ? $updated++ : $added++;
            } else {
                BulkInviteCampaignRecipient::query()->create($attrs + $vals);
                $added++;
            }
        });

        if (method_exists($campaign, 'updateCounts')) {
            $campaign->updateCounts();
        }

        Log::info('[ImportRecipients] Completed', [
            'campaign_id' => $campaign->id,
            'added' => $added,
            'updated' => $updated,
            'skipped_invalid' => $skippedInvalid,
        ]);
    }

    /** Resolve full path (tries public then default disk). */
    private function resolvePath(string $rel): ?string
    {
        if (Storage::disk('public')->exists($rel)) {
            return Storage::disk('public')->path($rel);
        }
        if (Storage::exists($rel)) {
            return Storage::path($rel);
        }

        return null;
    }

    /**
     * Extract msisdn (validated E.164), name, locale from headered or index-based rows.
     * Uses libphonenumber to parse/validate across GCC + Egypt.
     */
    private function extractRow(array $row, string $fallbackLocale): array
    {
        // Headered import: support common column names
        if (
            array_key_exists('msisdn', $row) ||
            array_key_exists('phone', $row) ||
            array_key_exists('mobile', $row) ||
            array_key_exists('whatsapp', $row)
        ) {
            $raw = $this->pick($row, ['msisdn', 'phone', 'mobile', 'whatsapp', 'contact', 'number']);
            $name = $this->pick($row, ['name', 'full_name', 'customer', 'contact_name']);
            $loc = strtolower((string) ($row['locale'] ?? $fallbackLocale));

            $e164 = $raw
                ? Phone::parseToE164AcrossRegions(
                    $raw,
                    $this->allowedRegions,
                    $this->defaultRegion,
                    $this->mobileOnly
                )
                : null;

            return [
                $e164,
                $this->trimOrNull($name),
                in_array($loc, ['en', 'ar'], true) ? $loc : $fallbackLocale,
            ];
        }

        // No header: expect 0=phone, 1=name, 2=locale
        $raw = $row[0] ?? $row['0'] ?? null;
        $name = $row[1] ?? $row['1'] ?? null;
        $loc = strtolower((string) ($row[2] ?? $row['2'] ?? $fallbackLocale));

        $e164 = $raw
            ? Phone::parseToE164AcrossRegions(
                (string) $raw,
                $this->allowedRegions,
                $this->defaultRegion,
                $this->mobileOnly
            )
            : null;

        return [
            $e164,
            $this->trimOrNull($name),
            in_array($loc, ['en', 'ar'], true) ? $loc : $fallbackLocale,
        ];
    }

    private function pick(array $row, array $keys): ?string
    {
        foreach ($keys as $k) {
            $val = Arr::get($row, $k);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        return null;
    }

    private function trimOrNull($v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}
