<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Seeder;

class PartnerPortalLinkSeeder extends Seeder
{
    public function run(): void
    {
        // pick any existing user
        $user = User::first();
        if (! $user) {
            return;
        }

        // pick or create a partner
        $partner = Partner::first() ?? Partner::create([
            'name' => ['en' => 'Demo Partner', 'ar' => 'شريك تجريبي'],
            'slug' => 'demo-partner',
            'is_active' => true,
        ]);

        // attach user to partner (pivot partner_user)
        $user->partners()->syncWithoutDetaching([$partner->id]);

        // attach branch role if a branch exists
        $branch = Branch::where('partner_id', $partner->id)->first();
        if ($branch) {
            $user->partnerBranches()->syncWithoutDetaching([
                $branch->id => ['role' => 'owner'],
            ]);
        }

        $this->command->info("Linked {$user->email} to partner {$partner->slug}.");
    }
}
