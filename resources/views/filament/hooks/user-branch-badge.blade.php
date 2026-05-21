@auth
    <style>
        /* Filament's topbar is z-20 by default, which collides with page-level
           sticky elements (e.g. WaitingPatients glass-header at z-20). When
           tied, the later DOM node wins — so the topbar's notification panel
           gets hidden behind page headers. Bumping to z-30 keeps the topbar
           (and its slide-over) above page content but still below modals
           opened from the page (z-40). */
        .fi-topbar { z-index: 30; }
    </style>
    @php
        $user = auth()->user();
        $isAdmin = method_exists($user, 'hasRole') && $user->hasRole(['admin', 'super_admin']);

        // For doctor users, their identity is their doctor branch — show that
        // and ignore branch_user / partner_user pivots so the header stays
        // clean ("Dr. X — Main Branch", no "+1" noise).
        $doctorBranchId = \Illuminate\Support\Facades\DB::table('doctors')
            ->where('user_id', $user->id)
            ->value('branch_id');

        if ($doctorBranchId) {
            $branchIds = collect([$doctorBranchId]);
        } else {
            $branchIds = \Illuminate\Support\Facades\DB::table('branch_user')
                ->where('user_id', $user->id)
                ->pluck('branch_id')
                ->merge(
                    \Illuminate\Support\Facades\DB::table('branches')
                        ->join('partner_user', 'branches.partner_id', '=', 'partner_user.partner_id')
                        ->where('partner_user.user_id', $user->id)
                        ->pluck('branches.id')
                )
                ->filter()
                ->unique()
                ->values();
        }

        $branchCount = $branchIds->count();
        $branchLabel = null;

        if ($branchCount >= 1) {
            $branchLabel = \App\Models\Branch::whereIn('id', $branchIds)
                ->orderBy('name')
                ->pluck('name')
                ->filter()
                ->implode(' · ');
        }
    @endphp

    <div
        class="hidden md:flex items-center gap-2 text-sm pointer-events-none fi-user-branch-badge"
        style="position: absolute; top: 0; left: 50%; transform: translateX(-50%); height: 100%; z-index: 1;"
    >
        <div class="flex flex-col items-center leading-tight pointer-events-auto">
            <span class="font-semibold text-gray-900 dark:text-white">
                {{ $user->name }}
            </span>
            <span class="text-xs text-gray-500 dark:text-gray-400">
                @if ($isAdmin)
                    {{ __('common.user_badge.all_branches') }}
                @elseif ($branchLabel)
                    {{ $branchLabel }}
                @else
                    {{ __('common.user_badge.no_branch') }}
                @endif
            </span>
        </div>
    </div>
@endauth
