<?php

namespace App\Wa\Jobs;

use App\Wa\Hub\Models\PromotionalCampaign;
use App\Wa\Hub\Models\PromotionalCampaignRecipient;
use App\Wa\Support\Phone;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\SimpleExcel\SimpleExcelReader;

class ImportBulkInviteRecipients implements /* NotTenantAware, */ ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $campaign = PromotionalCampaign::findOrFail($this->campaignId);

        $abs = $this->resolvePath($this->storedFilePath);
        if (! $abs || ! is_file($abs)) {
            Log::warning('[ImportRecipients] File not found', [
                'path' => $this->storedFilePath,
                'campaign_id' => $this->campaignId,
            ]);

            return;
        }

        Log::info('[ImportRecipients] Starting import', [
            'campaign_id' => $campaign->id,
            'file' => $abs,
            'has_header' => $this->hasHeader,
            'default_region' => $this->defaultRegion,
            'allowed_regions' => $this->allowedRegions,
            'mobile_only' => $this->mobileOnly,
        ]);

        $reader = SimpleExcelReader::create($abs);
        if (! $this->hasHeader) {
            $reader->noHeaderRow();
        }

        $added = 0;
        $updated = 0;
        $skippedInvalid = 0;
        $rowIndex = 0;
        $debugLimit = 20; // how many rows to log

        $reader->getRows()->each(function (array $row) use (
            $campaign,
            &$added,
            &$updated,
            &$skippedInvalid,
            &$rowIndex,
            $debugLimit
        ) {
            $rowIndex++;

            [$msisdn, $name, $locale] = $this->extractRow($row, (string) ($campaign->default_locale ?? 'en'));

            // Log some debug for the first few rows no matter what
            if ($rowIndex <= $debugLimit) {
                Log::debug('[ImportRecipients] Parsed row', [
                    'row_index' => $rowIndex,
                    'raw_row' => $row,
                    'msisdn' => $msisdn,
                    'name' => $name,
                    'locale' => $locale,
                ]);
            }

            if (! $msisdn) {
                $skippedInvalid++;

                if ($skippedInvalid <= $debugLimit) {
                    Log::debug('[ImportRecipients] Skipped invalid row (no msisdn)', [
                        'row_index' => $rowIndex,
                        'raw_row' => $row,
                        'fallback_locale' => $campaign->default_locale,
                    ]);
                }

                return;
            }

            $attrs = [
                'promotional_campaign_id' => $campaign->id,
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
                $existing = PromotionalCampaignRecipient::query()
                    ->where($attrs)
                    ->first();

                if ($existing) {
                    // Never resend if already sent
                    if ($existing->status === 'sent') {
                        // Optionally just update name/locale if you want
                        $existing->name = $name ?? $existing->name;
                        $existing->locale = $locale ?? $existing->locale;
                        $existing->save();

                        $updated++;

                        return;
                    }

                    // For pending/failed, we can safely reset to pending & clear errors
                    $existing->fill([
                        'name' => $name ?? $existing->name,
                        'locale' => $locale ?? $existing->locale,
                        'status' => 'pending',
                        'source' => 'excel',
                        'wa_message_id' => null,
                        'error_message' => null,
                    ])->save();

                    $updated++;
                } else {
                    PromotionalCampaignRecipient::query()->create($attrs + $vals);
                    $added++;
                }
            } else {
                PromotionalCampaignRecipient::query()->create($attrs + $vals);
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

            //  Always cast to string + log
            $rawStr = is_null($raw) ? '' : (string) $raw;

            Log::debug('[ImportRecipients] extractRow headered', [
                'raw' => $raw,
                'raw_str' => $rawStr,
                'fallback_locale' => $fallbackLocale,
            ]);

            // Primary parse using helper
            $e164 = $rawStr !== ''
                ? Phone::parseToE164AcrossRegions(
                    $rawStr,
                    $this->allowedRegions,
                    $this->defaultRegion,
                    $this->mobileOnly
                )
                : null;

            //  Extra debug when parsing fails
            if (! $e164 && $rawStr !== '') {
                Log::debug('[ImportRecipients] parseToE164AcrossRegions returned null', [
                    'raw_str' => $rawStr,
                    'defaultRegion' => $this->defaultRegion,
                    'allowedRegions' => $this->allowedRegions,
                    'mobileOnly' => $this->mobileOnly,
                ]);
            }

            //  Fallback for Kuwait local 8-digit numbers like 50300166 / 65556263
            if (! $e164 && $rawStr !== '' && preg_match('/^\d{8}$/', $rawStr) && $this->defaultRegion === 'KW') {
                $e164 = '+965'.$rawStr;

                Log::debug('[ImportRecipients] Applied KW fallback E.164', [
                    'raw_str' => $rawStr,
                    'e164' => $e164,
                ]);
            }

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

        $rawStr = is_null($raw) ? '' : (string) $raw;

        Log::debug('[ImportRecipients] extractRow index-based', [
            'raw' => $raw,
            'raw_str' => $rawStr,
        ]);

        $e164 = $rawStr !== ''
            ? Phone::parseToE164AcrossRegions(
                $rawStr,
                $this->allowedRegions,
                $this->defaultRegion,
                $this->mobileOnly
            )
            : null;

        if (! $e164 && $rawStr !== '' && preg_match('/^\d{8}$/', $rawStr) && $this->defaultRegion === 'KW') {
            $e164 = '+965'.$rawStr;

            Log::debug('[ImportRecipients] Applied KW fallback E.164 (index-based)', [
                'raw_str' => $rawStr,
                'e164' => $e164,
            ]);
        }

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

            if (is_null($val)) {
                continue;
            }

            // Strings: trim and use if non-empty
            if (is_string($val)) {
                $val = trim($val);
                if ($val !== '') {
                    return $val;
                }
            }

            // Numbers from Excel (int/float): cast to string
            if (is_int($val) || is_float($val)) {
                $val = trim((string) $val);
                if ($val !== '') {
                    return $val;
                }
            }
        }

        return null;
    }

    private function trimOrNull($v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}
