<?php

namespace App\Http\Controllers\Front\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    public function show()
    {
        return view('front.auth.register');
    }

    public function store(Request $request)
    {
        // Base rules
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:6'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_country_code' => ['nullable', 'string', 'max:8'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ];

        // Composite uniqueness on (phone, phone_country_code) if phone present
        if ($request->filled('phone')) {
            $rules['phone'][] = Rule::unique('users', 'phone')->where(function ($q) use ($request) {
                return $q->where('phone_country_code', (string) $request->input('phone_country_code'));
            });
        }

        $data = $request->validate($rules);

        $userClass = config('auth.providers.users.model', \App\Models\User::class);

        /** @var \App\Models\User $user */
        $user = $userClass::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'phone' => $data['phone'] ?? null,
            'phone_country_code' => $data['phone_country_code'] ?? null,
            'status' => 'active', // or 'inactive' if you want manual activation
            'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
        ]);

        // Assign Spatie role if available
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('customer');
        }

        // Send email verification link
        event(new Registered($user));

        // Keep user logged in; verified pages are gated elsewhere
        Auth::login($user);

        return redirect()
            ->route('verification.notice')
            ->with('status', __('A verification link was sent to your email.'));
    }
}
