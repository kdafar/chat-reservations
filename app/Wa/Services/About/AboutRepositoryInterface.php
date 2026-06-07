<?php

namespace App\Wa\Services\About;

use App\Wa\Hub\Models\AboutTemplate;
use App\Wa\Hub\Models\HubProfile;
use App\Wa\Hub\Models\Vendors;

interface AboutRepositoryInterface
{
    public function getActiveHubProfile(string $channel = 'whatsapp'): ?HubProfile;

    /** Resolve a template by scope+locale; if $code is provided, prefer that "family". */
    public function getTemplate(string $scope, string $locale, ?string $code = null, ?int $id = null): ?AboutTemplate;

    /** Resolve best template for a restaurant and locale. */
    public function getRestaurantTemplate(Vendors $restaurant, string $locale): ?AboutTemplate;
}
