<?php

namespace App\Wa\Filament\Resources\BulkSenderCampaignResource\Pages;

use App\Wa\Filament\Resources\BulkSenderCampaignResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditBulkSenderCampaign extends EditRecord
{
    protected static string $resource = BulkSenderCampaignResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->record;

        if (BulkSenderCampaignResource::isLocked($record)) {
            // lock these fields once sent/imported/queued
            $immutable = [
                'template_name',
                'template_details',
                'template_variables',
                'header_media_id',
                'header_image_path',
                'default_locale',
            ];

            foreach ($immutable as $key) {
                if (! array_key_exists($key, $data)) {
                    continue;
                }

                $incoming = $data[$key];
                $current = $record->{$key};

                // normalize curator picker state
                if ($key === 'header_media_id') {
                    $incoming = BulkSenderCampaignResource::extractCuratorMediaId($incoming);
                    $current = (int) ($current ?? 0);

                    if ((int) ($incoming ?? 0) !== $current) {
                        throw ValidationException::withMessages([
                            $key => __('This field cannot be changed after the campaign is sent/queued.'),
                        ]);
                    }

                    continue;
                }

                // arrays
                if (is_array($current) || is_array($incoming)) {
                    if ((array) $incoming !== (array) $current) {
                        throw ValidationException::withMessages([
                            $key => __('This field cannot be changed after the campaign is sent/queued.'),
                        ]);
                    }

                    continue;
                }

                // scalars
                if ((string) $incoming !== (string) $current) {
                    throw ValidationException::withMessages([
                        $key => __('This field cannot be changed after the campaign is sent/queued.'),
                    ]);
                }
            }

        }

        return $data;
    }
}
