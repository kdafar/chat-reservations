<?php

/*
|--------------------------------------------------------------------------
| Tenant identity
|--------------------------------------------------------------------------
|
| Everything that differs between one clinic install and the next. The goal is
| that a new clinic is a new .env + a new database — never a new git branch.
|
| Nothing here is read at request time by the product itself: the running app
| gets its brand copy from the `clinic.public.*` system settings (editable in
| v2 Settings) and its logo from `app.logo_url`. This config is what SEEDS
| those on a fresh install, plus what `brand:generate` uses to build the icon
| set. Treat it as install-time input, not runtime state.
|
| See TenantSetupSeeder, which turns this into the partner/branch/staff rows.
|
*/

return [

    /*
    | Stable identifier for this install. Used as the partner slug, so changing
    | it after go-live orphans the existing partner — pick once, keep it.
    */
    'slug' => env('TENANT_SLUG', 'clinic'),

    'name' => [
        'en' => env('TENANT_NAME_EN', env('APP_NAME', 'Clinic')),
        // Falls back to the English name so a single-language install works
        // without having to fill both.
        'ar' => env('TENANT_NAME_AR', env('TENANT_NAME_EN', env('APP_NAME', 'Clinic'))),
    ],

    'tagline' => [
        'en' => env('TENANT_TAGLINE_EN', ''),
        'ar' => env('TENANT_TAGLINE_AR', ''),
    ],

    /*
    | Shown on the public booking site. Leaving phone/whatsapp blank is
    | meaningful: the public site hides its "Call" buttons and shows "Book Now"
    | instead, so don't invent placeholder numbers here.
    */
    'contact' => [
        'email' => env('TENANT_EMAIL', ''),
        'phone' => env('TENANT_PHONE', ''),
        'whatsapp' => env('TENANT_WHATSAPP', ''),
        'website' => env('TENANT_WEBSITE', ''),
    ],

    'address' => [
        'en' => env('TENANT_ADDRESS_EN', ''),
        'ar' => env('TENANT_ADDRESS_AR', ''),
    ],

    'social' => [
        'instagram' => env('TENANT_INSTAGRAM', ''),
        'tiktok' => env('TENANT_TIKTOK', ''),
        'snapchat' => env('TENANT_SNAPCHAT', ''),
    ],

    /*
    | The first branch. Every install needs at least one — it becomes the stock
    | hub that inter-branch transfers resolve against. Further branches are
    | added through the admin, not here.
    */
    'branch' => [
        'slug' => env('TENANT_BRANCH_SLUG', env('TENANT_SLUG', 'clinic').'-main'),
        'name' => [
            'en' => env('TENANT_BRANCH_NAME_EN', env('TENANT_NAME_EN', env('APP_NAME', 'Clinic'))),
            'ar' => env('TENANT_BRANCH_NAME_AR', env('TENANT_BRANCH_NAME_EN', '')),
        ],
        // Matched against the `cities` table by LIKE; blank leaves it unset for
        // the clinic to pick in admin.
        'city' => env('TENANT_BRANCH_CITY', ''),
        'address' => env('TENANT_BRANCH_ADDRESS', env('TENANT_ADDRESS_EN', '')),
        'max_booking_days' => (int) env('TENANT_MAX_BOOKING_DAYS', 30),
        'default_room_name' => env('TENANT_DEFAULT_ROOM_NAME', 'Room 1'),
    ],

    /*
    | One starter user per role. Passwords come from the environment and are
    | never defaulted — TenantSetupSeeder refuses to run without one rather
    | than creating staff with a guessable password.
    */
    'staff' => [
        'email_domain' => env('TENANT_STAFF_EMAIL_DOMAIN', ''),
        'password' => env('TENANT_STAFF_PASSWORD'),
        // email-local => [display name, role]
        'accounts' => [
            'admin' => ['Clinic Administrator', 'clinic_admin'],
            'reception' => ['Reception', 'clinic_reception'],
            'doctor' => ['Doctor', 'clinic_doctor'],
            'nurse' => ['Nurse', 'clinic_nurse'],
            'accountant' => ['Accountant', 'accountant'],
            'lab' => ['Lab Technician', 'clinic_lab'],
        ],
    ],

    /*
    | Starter specialty catalogue. Generic on purpose — every clinic renames,
    | adds and removes these on the Services screen once it is running. This is
    | only here so the booking flow has something to offer on day one.
    |
    | [slug, english, arabic]
    */
    'services' => [
        ['general-medicine', 'General Medicine', 'الطب العام'],
        ['dermatology', 'Dermatology', 'الجلدية'],
        ['cosmetic-consultation', 'Cosmetic Consultation', 'استشارة تجميلية'],
        ['laser-hair-removal', 'Laser & Hair Removal', 'الليزر وإزالة الشعر'],
        ['injectables-fillers', 'Injectables & Fillers', 'الحقن والفيلر'],
        ['skin-treatments', 'Facial & Skin Treatments', 'علاجات البشرة والوجه'],
        ['laboratory', 'Laboratory', 'المختبر والتحاليل'],
    ],

    /*
    | Branding assets.
    |
    | `source_image` is the one file `php artisan brand:generate` derives the
    | whole icon set from (favicons, apple-touch, web-app manifest icons and the
    | wide logo). Those generated files are gitignored so each install owns its
    | own — that is what keeps one repo serving many brands.
    */
    'brand' => [
        'source_image' => env('TENANT_BRAND_IMAGE', 'brand-source.jpg'),
        'primary_color' => env('TENANT_PRIMARY_COLOR', '#b19860'),
        'theme_color' => env('TENANT_THEME_COLOR', '#ffffff'),
        // Short name under a phone home-screen icon; keep it under ~12 chars.
        'short_name' => env('TENANT_SHORT_NAME', env('TENANT_NAME_EN', env('APP_NAME', 'Clinic'))),
    ],

];
