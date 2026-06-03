<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Root Blade template for the v2 UI.
     *
     * @var string
     */
    protected $rootView = 'inertia/app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Default shared props for every Inertia response.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        $doctorId = null;
        $doctorBranchId = null;
        $doctorBranchName = null;
        $branches = [];
        if ($user) {
            $row = DB::table('doctors')
                ->where('doctors.user_id', $user->id)
                ->first(['doctors.id as doctor_id', 'doctors.branch_id']);

            $doctorId = $row->doctor_id ?? null;
            $doctorBranchId = $row->branch_id ?? null;

            // Pull the branch through Eloquent so HasTranslations resolves
            // the locale-aware name (raw DB::pluck would return JSON).
            if ($doctorBranchId) {
                $branch = \App\Models\Branch::find($doctorBranchId);
                $doctorBranchName = $branch
                    ? $branch->getTranslation('name', app()->getLocale(), true)
                    : null;
            }

            // All branches the user is attached to, for the topbar branch badge.
            // A doctor's identity IS their doctor branch (ignore other pivots so
            // the header stays clean). Everyone else: branch_user assignments plus
            // any branch under a partner they belong to. Mirrors the Filament
            // user-branch-badge hook so both UIs agree.
            if ($doctorBranchId) {
                $branchIds = collect([$doctorBranchId]);
            } else {
                $branchIds = DB::table('branch_user')
                    ->where('user_id', $user->id)
                    ->pluck('branch_id')
                    ->merge(
                        DB::table('branches')
                            ->join('partner_user', 'branches.partner_id', '=', 'partner_user.partner_id')
                            ->where('partner_user.user_id', $user->id)
                            ->pluck('branches.id')
                    )
                    ->filter()
                    ->unique()
                    ->values();
            }

            $locale = app()->getLocale();
            $branches = \App\Models\Branch::whereIn('id', $branchIds->all())
                ->orderBy('name')
                ->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->getTranslation('name', $locale, true),
                ])
                ->values()
                ->all();
        }

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->all() : [],
                    // Effective permission names, so the v2 sidebar can hide links the
                    // user can't actually open (controllers gate on these same perms).
                    // Server still enforces — this is a presentation hint only.
                    'permissions' => method_exists($user, 'getAllPermissions')
                        ? $user->getAllPermissions()->pluck('name')->all() : [],
                    // Role flags used by v2 UI to hide buttons the user can't trigger.
                    // Server still enforces — these are presentation hints only.
                    'is_admin' => method_exists($user, 'hasRole')
                        && ($user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('clinic_admin')),
                    'is_reception' => method_exists($user, 'hasRole')
                        && ($user->hasRole('clinic_reception')
                            || $user->hasRole('admin') || $user->hasRole('super_admin') || $user->hasRole('clinic_admin')),
                    // Per-table import permission, so the Import button hides
                    // where the user can't import. Server still enforces (403).
                    'can_import' => \App\Imports\V2\ImportRegistry::authorizationsFor($user),
                    'is_doctor' => $doctorId !== null,
                    'is_nurse' => method_exists($user, 'hasRole') && $user->hasRole('clinic_nurse'),
                    'doctor_id' => $doctorId,
                    'doctor_branch_id' => $doctorBranchId,
                    'doctor_branch_name' => $doctorBranchName,
                    // Every branch this user can work in: [{ id, name }, ...].
                    'branches' => $branches,
                ] : null,
            ],

            // App branding — driven by env vars so the header isn't hardcoded.
            //   APP_NAME      → header title (Laravel default)
            //   APP_LOGO_URL  → header logo image (defaults to /favicon.svg)
            'app' => [
                'name' => config('app.name'),
                'logo_url' => env('APP_LOGO_URL', '/favicon.svg'),
            ],

            'locale' => app()->getLocale(),
            'direction' => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',

            // Clinic operating hours + timezone, for the topbar clock / shift
            // indicator. Static config — cheap to share on every response.
            'clinic' => [
                'tz' => config('app.timezone', 'Asia/Kuwait'),
                'hours' => [
                    'open' => config('clinic.hours.open', '09:00'),
                    'close' => config('clinic.hours.close', '21:00'),
                ],
            ],

            // Live unread-notification count for the topbar bell. Lazy so it
            // only runs when actually shared (and only ever costs a single
            // indexed query against the notifications table).
            'unread_count' => fn () => $user
                ? DB::table('notifications')
                    ->where('notifiable_type', \App\Models\User::class)
                    ->where('notifiable_id', $user->id)
                    ->whereNull('read_at')
                    ->count()
                : 0,

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // v2 controllers flash a {type, message} payload; surface it so
                // FlashToasts can show a toast for every create/update/delete/action.
                'type' => fn () => $request->session()->get('flash')['type'] ?? null,
                'message' => fn () => $request->session()->get('flash')['message'] ?? null,
                // Optional { url, label } — FlashToasts renders an "Undo" button that POSTs to url.
                'undo' => fn () => $request->session()->get('flash')['undo'] ?? null,
            ],
        ];
    }
}
