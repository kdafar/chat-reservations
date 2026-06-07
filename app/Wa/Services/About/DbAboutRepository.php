<?php

namespace App\Wa\Services\About;

use App\Wa\Hub\Models\AboutTemplate;
use App\Wa\Hub\Models\HubProfile;
use App\Wa\Hub\Models\Vendors;

class DbAboutRepository implements AboutRepositoryInterface
{
    public function getActiveHubProfile(string $channel = 'whatsapp'): ?HubProfile
    {
        return HubProfile::query()
            ->where('channel', $channel)
            ->where('is_enabled', true)
            ->latest('updated_at')
            ->first();
    }

    public function getTemplate(string $scope, string $locale, ?string $code = null, ?int $id = null): ?AboutTemplate
    {
        if ($id) {
            $tpl = AboutTemplate::find($id);
            if ($tpl) {
                // Make sure we return matching locale version if grouped by code
                if ($tpl->code) {
                    return AboutTemplate::for($scope, $locale, $tpl->code);
                }

                return $tpl->scope === $scope ? $tpl : AboutTemplate::for($scope, $locale);
            }
        }

        return AboutTemplate::for($scope, $locale, $code);
    }

    public function getRestaurantTemplate(Vendors $restaurant, string $locale): ?AboutTemplate
    {
        // 1) Respect explicit selection by ID (and map to same code+locale if needed)
        if ($restaurant->about_template_id) {
            return $this->getTemplate('restaurant', $locale, null, $restaurant->about_template_id);
        }

        // 2) Otherwise pick first enabled for locale/scope
        return $this->getTemplate('restaurant', $locale);
    }
}
