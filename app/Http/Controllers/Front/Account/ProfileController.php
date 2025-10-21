<?php

namespace App\Http\Controllers\Front\Account;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user()->load('defaultAddress');

        return view('front.account.profile.edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = $request->user();

        // ----- Password update flow -----
        if ($request->input('action') === 'password') {
            $data = $request->validate([
                'current_password' => ['required'],
                'password' => ['required', 'confirmed', 'min:6'],
            ]);

            if (! Hash::check($data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => __('The current password is incorrect.')]);
            }

            // 'password' is auto-hashed via casts in User model
            $user->update(['password' => $data['password']]);

            return back()->with('success_password', __('Your password has been updated.'));
        }

        // ----- Profile details update flow -----
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_country_code' => ['nullable', 'string', 'max:8'],
            'marketing_opt_in' => ['sometimes', 'boolean'],
        ];

        // Composite uniqueness for (phone, phone_country_code)
        if ($request->filled('phone')) {
            $rules['phone'][] = Rule::unique('users', 'phone')
                ->where(fn ($q) => $q->where('phone_country_code', (string) $request->input('phone_country_code')))
                ->ignore($user->id);
        }

        $data = $request->validate($rules);

        $emailChanged = strcasecmp($data['email'], $user->email) !== 0;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'phone_country_code' => $data['phone_country_code'] ?? null,
            'marketing_opt_in' => (bool) ($data['marketing_opt_in'] ?? false),
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null; // force re-verification if email changed
        }

        $user->save();

        if ($emailChanged && $user instanceof MustVerifyEmail) {
            $user->sendEmailVerificationNotification();

            return back()->with('status', __('We sent a new verification link to your updated email.'));
        }

        return back()->with('success', __('Profile updated.'));
    }
}
