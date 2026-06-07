<?php

use App\Http\Controllers\Admin\BulkInviteSamplesController;
use App\Http\Controllers\Admin\VisitPrintController;
use App\Http\Controllers\BookingCheckInController;
// Route::get('/', function (Request $request) {
//     $qs = $request->getQueryString();
//     return redirect('/en' . ($qs ? ('?' . $qs) : ''), 302);
// });

// ----- FRONT CONTROLLERS -----
use App\Http\Controllers\BookingPassController;
use App\Http\Controllers\BookingQrController;
use App\Http\Controllers\BookingReceiptController;
use App\Http\Controllers\Front\Account\AddressController;
use App\Http\Controllers\Front\Account\ProfileController;
use App\Http\Controllers\Front\Auth\LoginController;
use App\Http\Controllers\Front\Auth\LogoutController;
use App\Http\Controllers\Front\Auth\RegisterController;
use App\Http\Controllers\Front\BranchMenuController;
use App\Http\Controllers\Front\CartController;
use App\Http\Controllers\Front\CartCouponController;
use App\Http\Controllers\Front\CheckoutController;
use App\Http\Controllers\Front\CheckoutStateController;
use App\Http\Controllers\Front\ClinicBookingController;
use App\Http\Controllers\Front\GeoController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\LocationController;
use App\Http\Controllers\Front\OrderController;
use App\Http\Controllers\Front\PaymentController;
use App\Http\Controllers\Front\ServiceBrowseController;
use App\Http\Controllers\PaymentCallbackController;
use App\Models\Booking;
use App\Services\QrPassService;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ---------- LANGUAGE ----------
Route::get('/language/{locale}', function (string $locale, \Illuminate\Http\Request $request) {
    abort_unless(in_array($locale, ['ar', 'en']), 404);

    // Write BOTH session keys so every locale-reader in the app (the v2 web
    // middleware reads `lang`; the Filament admin middleware reads `locale`)
    // sees the change. Cheap insurance against future drift.
    session()->put('lang', $locale);
    session()->put('locale', $locale);

    if ($user = $request->user()) {
        $user->forceFill(['preferred_locale' => $locale])->save();
    }

    return back();
})->name('language.switch');

// Admin/Filament locale switcher: persists to authenticated user's
// preferred_locale column AND updates the session, then returns the user
// to wherever they came from.
Route::get('/locale/switch', function (\Illuminate\Http\Request $request) {
    $lang = $request->query('lang');
    abort_unless(in_array($lang, ['ar', 'en'], true), 404);

    session(['locale' => $lang]);

    if ($user = $request->user()) {
        $user->forceFill(['preferred_locale' => $lang])->save();
    }

    return redirect($request->headers->get('referer') ?? '/admin');
})->middleware('web')->name('locale.switch');

Route::get('/bookings/{code}', [BookingPassController::class, 'show'])->name('bookings.pass');
Route::get('/qr/{token}.png', [BookingQrController::class, 'image'])->name('bookings.qr');
Route::get('/c/{token}', [BookingCheckInController::class, 'fromLink'])->name('bookings.checkin.link');

// PNG QR used by WhatsApp (must be public HTTPS)
Route::get('/bookings/{code}/qr.png', function (string $code) {
    $b = Booking::where('booking_code', $code)->firstOrFail();

    return app(QrPassService::class)->qrPngResponse($b, 600); // 600px QR
})->name('bookings.qr.png');

// All front routes go through the default "web" stack.
// Your SetLocaleFromSession middleware is appended to the web group in bootstrap/app.php

// ---------- PUBLIC BROWSE (guest + auth) ----------
Route::middleware(['web'])->group(function () {
    // Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/', function () {
        return view('clinic.landing');
    })->name('home');
    Route::get('/services/{service:slug}', [ServiceBrowseController::class, 'index'])->name('service.browse');
    Route::get('/services/{service:slug}/branches/{branch}', [BranchMenuController::class, 'show'])->name('branch.menu');

    // Location (store selected city/block in session)
    Route::post('/location/set', [LocationController::class, 'store'])->name('location.set');

    // ---------- CART (guest-friendly) ----------
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::get('/cart/lines', [CartController::class, 'lines'])->name('cart.lines');
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.items.store');        // add item
    Route::patch('/cart/items/{rowId}', [CartController::class, 'update'])->name('cart.items.update');      // qty/modifiers
    Route::delete('/cart/items/{rowId}', [CartController::class, 'destroy'])->name('cart.items.destroy');    // remove
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');              // clear
    Route::get('/cart/summary', [CartController::class, 'summary'])->name('cart.summary');          // ajax summary (optional)
    Route::post('/cart/address', [CartController::class, 'setAddress'])->name('cart.address');

    Route::post('/cart/coupon/apply', [CartCouponController::class, 'apply'])->name('cart.coupon.apply');
    Route::post('/cart/coupon/remove', [CartCouponController::class, 'remove'])->name('cart.coupon.remove');
    // ---------- CHECKOUT (GUEST + AUTH) ----------
    // Guests can place orders. We'll capture name/phone/address at POST /checkout.
    Route::post('/checkout/order-type', [CheckoutStateController::class, 'saveOrderType'])->name('checkout.state.order_type');
    Route::post('/checkout/address', [CheckoutStateController::class, 'saveAddress'])->name('checkout.state.address');
    Route::post('/checkout/phone', [CheckoutStateController::class, 'savePhone'])->name('checkout.state.phone');
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');   // show form (guest allowed)
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');   // create order (guest or auth)

    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');

    Route::get('/orders/{order}/status', [OrderController::class, 'status'])
        ->middleware(['signed', 'throttle:30,1']) // signed link + 30 req/min
        ->name('orders.status');

    Route::post('/orders/{order}/pay', [OrderController::class, 'pay'])
        ->middleware(['throttle:10,1'])
        ->name('orders.pay');

    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])
        ->middleware(['throttle:10,1'])
        ->name('orders.cancel');

    Route::post('/orders/{order}/reorder', [OrderController::class, 'reorder'])
        ->middleware(['throttle:10,1'])
        ->name('orders.reorder');

    Route::patch('/cart/items/{rowId}', [CartController::class, 'update'])
        ->where('rowId', '[a-f0-9]{64}')
        ->name('cart.items.update');

    Route::delete('/cart/items/{rowId}', [CartController::class, 'destroy'])
        ->where('rowId', '[a-f0-9]{64}')
        ->name('cart.items.destroy');

    // ---------- PAYMENTS ----------
    // Start payment (e.g., MyFatoorah), provider callback/webhook, and success/error pages.
    Route::post('/payments/start', [PaymentController::class, 'start'])->name('payments.start');

    Route::match(['GET', 'POST'], '/payments/callback/{driver}', [PaymentController::class, 'callback'])
        ->name('payments.callback'); // driver = myfatoorah|tap|stripe|cash

    Route::match(['POST'], '/payments/webhook/{driver}/{account}', [PaymentController::class, 'webhook'])
        ->name('payments.webhook'); // secure by secret in provider console (IP allowlist or signed URL)

    Route::get('/payments/success', [PaymentController::class, 'success'])->name('payment.success');
    Route::get('/payments/error', [PaymentController::class, 'error'])->name('payment.error');
});

Route::prefix('bookings/payment')->name('bookings.payment.')->group(function () {
    Route::get('/finalize', [PaymentCallbackController::class, 'finalize'])->name('finalize');
    Route::get('/failed', [PaymentCallbackController::class, 'failed'])->name('failed');
});

Route::get('/bookings/{booking}/receipt', [BookingReceiptController::class, 'show'])
    ->name('bookings.receipt.show')
    ->middleware('auth');

// ---------- AUTH (guest-only pages) ----------
// Only registered when the public customer portal is enabled. The clinic's only
// real login is the Filament admin login at /admin/login; guests hitting admin
// pages are redirected there (bootstrap/app.php redirectGuestsTo). See
// config('clinic.customer_portal_enabled').
if (config('clinic.customer_portal_enabled')) {
    Route::middleware(['web', 'guest.front'])->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'authenticate'])->name('login.attempt');
        Route::get('/register', [RegisterController::class, 'show'])->name('register');
        Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
    });
}

Route::group(['prefix' => 'clinic', 'middleware' => ['web']], function () {
    // The Page
    Route::get('/book', [ClinicBookingController::class, 'index'])->name('clinic.book');

    // The API Endpoints (called by React)
    Route::get('/api/partners', [ClinicBookingController::class, 'partners']);
    Route::get('/api/branches', [ClinicBookingController::class, 'branches']);
    Route::get('/api/doctors', [ClinicBookingController::class, 'doctors']);
    Route::get('/api/slots', [ClinicBookingController::class, 'slots']);

    // Actions
    Route::post('/api/bookings/request-otp', [ClinicBookingController::class, 'requestOtp']);
    Route::post('/api/bookings', [ClinicBookingController::class, 'store']);
    Route::post('/api/bookings/cancel', [ClinicBookingController::class, 'cancel']);

    Route::get('/api/services', [ClinicBookingController::class, 'services']);
    Route::get('/api/branches/index', [ClinicBookingController::class, 'branchesIndex']);
    Route::get('/api/branches/{branch:slug}', [ClinicBookingController::class, 'branchShow']);
    Route::get('/api/doctors/{doctor}', [ClinicBookingController::class, 'doctorShow']);
});

Route::get('/clinic', function () {
    return view('clinic.landing');
});

// ---------- AUTH-ONLY (customer account pages) ----------
Route::middleware(['web', 'ensure.customer'])->group(function () {

    // Account area (requires auth; orders listing, addresses, etc.)
    Route::get('/my/orders', [OrderController::class, 'index'])->name('account.orders');
    Route::get('/my/orders/{order}', [OrderController::class, 'show'])->name('account.orders.show');
    Route::get('/my/orders/{order}/status', [OrderController::class, 'status'])->name('account.orders.status');
    Route::post('/my/orders/{order}/pay', [OrderController::class, 'pay'])->name('account.orders.pay');
    Route::post('/my/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('account.orders.cancel');
    Route::post('/my/orders/{order}/reorder', [OrderController::class, 'reorder'])->name('account.orders.reorder');
    Route::get('/my/profile', [ProfileController::class, 'edit'])->name('account.profile.edit');
    Route::patch('/my/profile', [ProfileController::class, 'update'])->name('account.profile.update');

    Route::prefix('/my/addresses')->name('account.addresses.')->group(function () {
        Route::get('/', [AddressController::class, 'index'])->name('index');
        Route::get('/create', [AddressController::class, 'create'])->name('create');
        Route::post('/', [AddressController::class, 'store'])->name('store');
        Route::get('/{address}/edit', [AddressController::class, 'edit'])->name('edit');
        Route::patch('/{address}', [AddressController::class, 'update'])->name('update');
        Route::delete('/{address}', [AddressController::class, 'destroy'])->name('destroy');
        Route::patch('/{address}/default', [AddressController::class, 'setDefault'])->name('default');
    });

    // If you need phone-verified gates for specific pages later:
    // Route::middleware('ensure.phoneVerified')->get('/checkout/confirm', [CheckoutController::class, 'confirm'])->name('checkout.confirm');
});

Route::get('/partners/apply', function () {
    return view('front.partners.apply');
})->name('partners.apply');

// Careers landing / jobs listing
Route::get('/careers', function () {
    return view('front.careers.index');
})->name('careers.index');

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('logout');
    // notice page
    Route::get('/email/verify', function () {
        return view('front.auth.verify-email');
    })->name('verification.notice');

    // signed verification link
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
        $request->fulfill(); // sets email_verified_at

        return redirect()->intended(route('home'))->with('success', __('Email verified!'));
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    // resend verification email
    Route::post('/email/verification-notification', function (Request $request) {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('home'));
        }
        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    })->middleware(['throttle:6,1'])->name('verification.send');
});

Route::middleware(['web', 'auth'])->prefix('medical')->name('medical.')->group(function () {
    Route::get('visits/{visit}/print/prescription', [VisitPrintController::class, 'prescription'])
        ->name('visits.print.prescription');

    Route::get('visits/{visit}/print/labs', [VisitPrintController::class, 'labs'])
        ->name('visits.print.labs');

    Route::get('visits/{visit}/print/medical-leave', [VisitPrintController::class, 'medicalLeave'])
        ->name('visits.print.medical-leave');
});

Route::prefix('api/geo')->middleware(['web'])->group(function () {
    Route::get('/states', [GeoController::class, 'states'])->name('geo.states');
    Route::get('/cities', [GeoController::class, 'cities'])->name('geo.cities');   // ?state_id=
    Route::get('/blocks', [GeoController::class, 'blocks'])->name('geo.blocks');   // ?city_id=
    Route::get('/nearest', [GeoController::class, 'nearest'])->name('geo.nearest'); // ?lat=&lng=
});

Route::middleware(['web', 'auth', 'verified', App\Http\Middleware\PartnerContext::class])
    ->get('/partner/import/template', function () {
        $path = storage_path('app/import-templates/branch-menu-template.xlsx'); // put your template here
        abort_unless(file_exists($path), 404, 'Template not found');

        return response()->download($path, 'menu-items-template.xlsx');
    })
    ->name('partner.import.template');

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/admin/campaigns/{campaign}/recipients/sample.csv', [BulkInviteSamplesController::class, 'csv'])
        ->name('bulk-invite.sample.csv');

    Route::get('/admin/campaigns/{campaign}/recipients/sample.xlsx', [BulkInviteSamplesController::class, 'xlsx'])
        ->name('bulk-invite.sample.xlsx');

    Route::get('/admin/patient-files/{patientFile}/download', [\App\Http\Controllers\PatientFileController::class, 'download'])
        ->name('admin.patient-files.download');
});

/*
|--------------------------------------------------------------------------
| v2 UI (Inertia + Vue) — parallel to Filament admin, same admin namespace
|--------------------------------------------------------------------------
| Lives under /admin/v2/*. Same access gating as the Filament panel:
| authenticated, active status, and at least one role assigned. Once a v2
| screen is proven in production, comment out the matching Filament page
| registration in AdminPanelProvider.
*/
Route::middleware([
    'web',
    'auth',
    \App\Http\Middleware\EnsureCanAccessAdminPanel::class,
])->prefix('admin/v2')->name('v2.')->group(function () {
    // Dashboard landing.
    Route::get('/dashboard', [\App\Http\Controllers\V2\DashboardController::class, 'index'])->name('dashboard');

    Route::get('/waiting-patients', [\App\Http\Controllers\V2\WaitingPatientsController::class, 'index'])
        ->name('waiting-patients');

    Route::get('/visits/{visit}', [\App\Http\Controllers\V2\VisitConsoleController::class, 'show'])
        ->name('visits.show');
    Route::get('/api/visits/{visit}', [\App\Http\Controllers\V2\VisitConsoleController::class, 'showJson'])->name('api.visits.show');
    Route::post('/api/visits/{visit}/update',   [\App\Http\Controllers\V2\VisitConsoleController::class, 'update'])->name('api.visits.update');
    Route::post('/api/visits/{visit}/start',    [\App\Http\Controllers\V2\VisitConsoleController::class, 'start'])->name('api.visits.start');
    Route::post('/api/visits/{visit}/reassign-doctor', [\App\Http\Controllers\V2\VisitConsoleController::class, 'reassignDoctor'])->name('api.visits.reassign-doctor');
    Route::post('/api/visits/{visit}/complete', [\App\Http\Controllers\V2\VisitConsoleController::class, 'complete'])->name('api.visits.complete');
    Route::get('/api/visits/{visit}/clinic-items', [\App\Http\Controllers\V2\VisitConsoleController::class, 'clinicItems'])->name('api.visits.clinic-items');
    Route::get('/api/visits/{visit}/clinic-packages', [\App\Http\Controllers\V2\VisitConsoleController::class, 'clinicPackages'])->name('api.visits.clinic-packages');
    Route::post('/api/visits/{visit}/items',           [\App\Http\Controllers\V2\VisitConsoleController::class, 'addItem'])->name('api.visits.items.add');
    Route::post('/api/visits/{visit}/items/{item}',    [\App\Http\Controllers\V2\VisitConsoleController::class, 'updateItem'])->name('api.visits.items.update');
    Route::delete('/api/visits/{visit}/items/{item}',  [\App\Http\Controllers\V2\VisitConsoleController::class, 'deleteItem'])->name('api.visits.items.delete');
    Route::post('/api/visits/{visit}/packages',                [\App\Http\Controllers\V2\VisitConsoleController::class, 'addPackage'])->name('api.visits.packages.add');
    Route::post('/api/visits/{visit}/packages/{package}',      [\App\Http\Controllers\V2\VisitConsoleController::class, 'updatePackage'])->name('api.visits.packages.update');
    Route::delete('/api/visits/{visit}/packages/{package}',    [\App\Http\Controllers\V2\VisitConsoleController::class, 'deletePackage'])->name('api.visits.packages.delete');
    // Visit-level discount + coupon (billing edits, allowed at checkout).
    Route::post('/api/visits/{visit}/discount', [\App\Http\Controllers\V2\VisitConsoleController::class, 'setDiscount'])->name('api.visits.discount');
    Route::post('/api/visits/{visit}/coupon',   [\App\Http\Controllers\V2\VisitConsoleController::class, 'applyCoupon'])->name('api.visits.coupon.apply');
    Route::delete('/api/visits/{visit}/coupon', [\App\Http\Controllers\V2\VisitConsoleController::class, 'removeCoupon'])->name('api.visits.coupon.remove');
    Route::post('/api/visits/{visit}/payments',                [\App\Http\Controllers\V2\VisitConsoleController::class, 'addPayment'])->name('api.visits.payments.add');
    Route::post('/api/visits/{visit}/payments/{payment}/void', [\App\Http\Controllers\V2\VisitConsoleController::class, 'voidPayment'])->name('api.visits.payments.void');
    Route::get('/api/visits/{visit}/insurance/estimate',       [\App\Http\Controllers\V2\VisitConsoleController::class, 'estimateInsurance'])->name('api.visits.insurance.estimate');
    Route::post('/api/visits/{visit}/insurance/apply',         [\App\Http\Controllers\V2\VisitConsoleController::class, 'applyInsurance'])->name('api.visits.insurance.apply');
    Route::post('/api/visits/{visit}/request-stock', [\App\Http\Controllers\V2\VisitConsoleController::class, 'requestStock'])->name('api.visits.request-stock');
    Route::post('/api/visits/{visit}/source-from-hub', [\App\Http\Controllers\V2\VisitConsoleController::class, 'sourceFromHub'])->name('api.visits.source-from-hub');
    Route::post('/api/visits/{visit}/fulfill-stock', [\App\Http\Controllers\V2\VisitConsoleController::class, 'fulfillStock'])->name('api.visits.fulfill-stock');
    Route::post('/api/visits/{visit}/discharge', [\App\Http\Controllers\V2\VisitConsoleController::class, 'discharge'])->name('api.visits.discharge');
    Route::post('/api/visits/{visit}/insurance/create-claim', [\App\Http\Controllers\V2\VisitConsoleController::class, 'createInsuranceClaim'])->name('api.visits.insurance.create-claim');
    Route::post('/api/visits/{visit}/insurance/skip', [\App\Http\Controllers\V2\VisitConsoleController::class, 'skipInsuranceClaim'])->name('api.visits.insurance.skip');
    // Clinical fast-fill helpers: quick-phrase library, drug formulary, lab catalog.
    Route::get('/api/visits/{visit}/phrases',  [\App\Http\Controllers\V2\VisitConsoleController::class, 'phrases'])->name('api.visits.phrases');
    Route::post('/api/visits/{visit}/phrases', [\App\Http\Controllers\V2\VisitConsoleController::class, 'savePhrase'])->name('api.visits.phrases.save');
    Route::post('/api/visits/{visit}/phrases/{phrase}/use', [\App\Http\Controllers\V2\VisitConsoleController::class, 'usePhrase'])->name('api.visits.phrases.use');
    Route::get('/api/visits/{visit}/medications', [\App\Http\Controllers\V2\VisitConsoleController::class, 'medications'])->name('api.visits.medications');
    Route::post('/api/visits/{visit}/medications/{medication}/use', [\App\Http\Controllers\V2\VisitConsoleController::class, 'useMedication'])->name('api.visits.medications.use');
    Route::get('/api/visits/{visit}/lab-tests', [\App\Http\Controllers\V2\VisitConsoleController::class, 'labTests'])->name('api.visits.lab-tests');

    Route::get('/patients', [\App\Http\Controllers\V2\PatientsController::class, 'index'])->name('patients.index');
    Route::post('/patients', [\App\Http\Controllers\V2\PatientsController::class, 'store'])->name('patients.store');
    Route::get('/api/patients/{patient}', [\App\Http\Controllers\V2\PatientsController::class, 'quickView'])->name('api.patients.show');
    Route::put('/patients/{patient}', [\App\Http\Controllers\V2\PatientsController::class, 'update'])->name('patients.update');
    Route::get('/patients/{patient}', [\App\Http\Controllers\V2\PatientsController::class, 'show'])
        ->name('patients.show');

    // Patient files (v2).
    Route::get('/api/patients/{patient}/files',  [\App\Http\Controllers\V2\PatientFilesController::class, 'list'])->name('api.patient-files.list');
    Route::post('/api/patients/{patient}/files', [\App\Http\Controllers\V2\PatientFilesController::class, 'store'])->name('api.patient-files.store');
    Route::post('/api/patient-files/{patientFile}',          [\App\Http\Controllers\V2\PatientFilesController::class, 'update'])->name('api.patient-files.update');
    Route::delete('/api/patient-files/{patientFile}',        [\App\Http\Controllers\V2\PatientFilesController::class, 'destroy'])->name('api.patient-files.destroy');
    Route::get('/api/patient-files/{patientFile}/access-logs', [\App\Http\Controllers\V2\PatientFilesController::class, 'accessLogs'])->name('api.patient-files.access-logs');
    Route::get('/api/patient-files/{patientFile}/download', [\App\Http\Controllers\V2\PatientFilesController::class, 'download'])->name('api.patient-files.download');

    // Bookings list + create.
    Route::get('/bookings', [\App\Http\Controllers\V2\BookingsController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/new', [\App\Http\Controllers\V2\BookingsController::class, 'create'])->name('bookings.create');
    Route::post('/bookings', [\App\Http\Controllers\V2\BookingsController::class, 'store'])->name('bookings.store');
    Route::get('/api/bookings', [\App\Http\Controllers\V2\BookingsController::class, 'list'])->name('api.bookings.list');
    Route::get('/api/bookings/form-options', [\App\Http\Controllers\V2\BookingsController::class, 'formOptions'])->name('api.bookings.form-options');
    Route::get('/api/bookings/slots', [\App\Http\Controllers\V2\BookingsController::class, 'slots'])->name('api.bookings.slots');
    Route::get('/api/bookings/{booking}', [\App\Http\Controllers\V2\BookingsController::class, 'show'])->name('api.bookings.show');
    Route::post('/api/bookings/{booking}/cancel', [\App\Http\Controllers\V2\BookingsController::class, 'cancel'])->name('api.bookings.cancel');
    Route::post('/api/bookings/{booking}/no-show', [\App\Http\Controllers\V2\BookingsController::class, 'markNoShow'])->name('api.bookings.no-show');
    Route::post('/api/bookings/{booking}/reschedule', [\App\Http\Controllers\V2\BookingsController::class, 'reschedule'])->name('api.bookings.reschedule');
    Route::put('/api/bookings/{booking}', [\App\Http\Controllers\V2\BookingsController::class, 'update'])->name('api.bookings.update');
    Route::get('/api/bookings/{booking}/rooms',     [\App\Http\Controllers\V2\BookingsController::class, 'rooms'])->name('api.bookings.rooms');
    Route::post('/api/bookings/{booking}/assign-room', [\App\Http\Controllers\V2\BookingsController::class, 'assignRoom'])->name('api.bookings.assign-room');
    Route::post('/api/bookings/{booking}/collect-consultation', [\App\Http\Controllers\V2\BookingsController::class, 'collectConsultation'])->name('api.bookings.collect');
    Route::post('/api/bookings/{booking}/resend-confirmation', [\App\Http\Controllers\V2\BookingsController::class, 'resendConfirmation'])->name('api.bookings.resend');

    // Reception check-in wizard.
    Route::get('/checkin', [\App\Http\Controllers\V2\CheckinController::class, 'index'])->name('checkin');
    Route::get('/api/checkin/search', [\App\Http\Controllers\V2\CheckinController::class, 'search'])->name('api.checkin.search');
    Route::get('/api/checkin/bookings/{booking}', [\App\Http\Controllers\V2\CheckinController::class, 'booking'])->name('api.checkin.booking');
    Route::get('/api/checkin/bookings/{booking}/rooms', [\App\Http\Controllers\V2\CheckinController::class, 'rooms'])->name('api.checkin.rooms');
    Route::post('/api/checkin/bookings/{booking}/collect-fee', [\App\Http\Controllers\V2\CheckinController::class, 'collectFee'])->name('api.checkin.collect-fee');
    Route::post('/api/checkin/bookings/{booking}/check-in', [\App\Http\Controllers\V2\CheckinController::class, 'checkin'])->name('api.checkin.check-in');

    // JSON endpoints for the v2 notifications subsystem.
    Route::get('/api/notifications/poll',   [\App\Http\Controllers\V2\NotificationsController::class, 'poll'])->name('api.notifications.poll');
    Route::get('/api/notifications/recent', [\App\Http\Controllers\V2\NotificationsController::class, 'recent'])->name('api.notifications.recent');
    Route::post('/api/notifications/{id}/read',     [\App\Http\Controllers\V2\NotificationsController::class, 'markRead'])->name('api.notifications.read');
    Route::post('/api/notifications/read-all',      [\App\Http\Controllers\V2\NotificationsController::class, 'markAllRead'])->name('api.notifications.read-all');

    // ⌘K global search.
    Route::get('/api/search', [\App\Http\Controllers\V2\SearchController::class, 'index'])->name('api.search');

    // "How to use this page" help content (dedicated v2 help slide-over).
    Route::get('/api/help/{key}', [\App\Http\Controllers\V2\HelpController::class, 'show'])->name('api.help.show');

    // Live topbar status chips (waiting / today's bookings / unpaid).
    Route::get('/api/summary', [\App\Http\Controllers\V2\SummaryController::class, 'summary'])->name('api.summary');

    // Lab tests catalog (v2 replacement for Filament LabTestResource).
    Route::get('/lab-tests',                  [\App\Http\Controllers\V2\LabTestsController::class, 'index'])->name('lab-tests.index');
    Route::post('/lab-tests',                 [\App\Http\Controllers\V2\LabTestsController::class, 'store'])->name('lab-tests.store');
    Route::put('/lab-tests/{labTest}',        [\App\Http\Controllers\V2\LabTestsController::class, 'update'])->name('lab-tests.update');
    Route::delete('/lab-tests/{labTest}',     [\App\Http\Controllers\V2\LabTestsController::class, 'destroy'])->name('lab-tests.destroy');
    Route::post('/lab-tests/{labTest}/restore', [\App\Http\Controllers\V2\LabTestsController::class, 'restore'])->name('lab-tests.restore');

    // Staff leaves (v2 replacement for StaffLeaveResource).
    Route::get('/staff-leaves',                   [\App\Http\Controllers\V2\StaffLeavesController::class, 'index'])->name('staff-leaves.index');
    Route::post('/staff-leaves',                  [\App\Http\Controllers\V2\StaffLeavesController::class, 'store'])->name('staff-leaves.store');
    Route::put('/staff-leaves/{staffLeave}',      [\App\Http\Controllers\V2\StaffLeavesController::class, 'update'])->name('staff-leaves.update');
    Route::post('/staff-leaves/{staffLeave}/decide', [\App\Http\Controllers\V2\StaffLeavesController::class, 'decide'])->name('staff-leaves.decide');
    Route::delete('/staff-leaves/{staffLeave}',   [\App\Http\Controllers\V2\StaffLeavesController::class, 'destroy'])->name('staff-leaves.destroy');

    // Staff attendance (v2 replacement for StaffAttendanceResource).
    Route::get('/staff-attendances',                              [\App\Http\Controllers\V2\StaffAttendancesController::class, 'index'])->name('staff-attendances.index');
    Route::post('/staff-attendances/clock-in',                    [\App\Http\Controllers\V2\StaffAttendancesController::class, 'clockInSelf'])->name('staff-attendances.clock-in');
    Route::post('/staff-attendances/{staffAttendance}/clock-out', [\App\Http\Controllers\V2\StaffAttendancesController::class, 'clockOut'])->name('staff-attendances.clock-out');
    Route::put('/staff-attendances/{staffAttendance}',            [\App\Http\Controllers\V2\StaffAttendancesController::class, 'update'])->name('staff-attendances.update');
    Route::delete('/staff-attendances/{staffAttendance}',         [\App\Http\Controllers\V2\StaffAttendancesController::class, 'destroy'])->name('staff-attendances.destroy');

    // Doctors (v2 replacement for DoctorResource).
    Route::get('/doctors',                   [\App\Http\Controllers\V2\DoctorsController::class, 'index'])->name('doctors.index');
    Route::post('/doctors',                  [\App\Http\Controllers\V2\DoctorsController::class, 'store'])->name('doctors.store');
    Route::put('/doctors/{doctor}',          [\App\Http\Controllers\V2\DoctorsController::class, 'update'])->name('doctors.update');
    Route::delete('/doctors/{doctor}',       [\App\Http\Controllers\V2\DoctorsController::class, 'destroy'])->name('doctors.destroy');
    Route::post('/doctors/{doctor}/restore', [\App\Http\Controllers\V2\DoctorsController::class, 'restore'])->name('doctors.restore');

    // Users (v2 replacement for UserResource). Admin only.
    Route::get('/users',             [\App\Http\Controllers\V2\UsersController::class, 'index'])->name('users.index');
    Route::post('/users',            [\App\Http\Controllers\V2\UsersController::class, 'store'])->name('users.store');
    Route::put('/users/{user}',      [\App\Http\Controllers\V2\UsersController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}',   [\App\Http\Controllers\V2\UsersController::class, 'destroy'])->name('users.destroy');

    // Roles & Permissions (v2 replacement for RoleResource). Admin only.
    Route::get('/roles',             [\App\Http\Controllers\V2\RolesController::class, 'index'])->name('roles.index');
    Route::post('/roles',            [\App\Http\Controllers\V2\RolesController::class, 'store'])->name('roles.store');
    Route::put('/roles/{role}',      [\App\Http\Controllers\V2\RolesController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}',   [\App\Http\Controllers\V2\RolesController::class, 'destroy'])->name('roles.destroy');

    // Branches (clinic-focused v2 replacement for BranchResource). Admin only.
    Route::get('/branches',              [\App\Http\Controllers\V2\BranchesController::class, 'index'])->name('branches.index');
    Route::post('/branches',             [\App\Http\Controllers\V2\BranchesController::class, 'store'])->name('branches.store');
    Route::put('/branches/{branch}',     [\App\Http\Controllers\V2\BranchesController::class, 'update'])->name('branches.update');
    Route::delete('/branches/{branch}',  [\App\Http\Controllers\V2\BranchesController::class, 'destroy'])->name('branches.destroy');

    // Doctor Schedule (v2 replacement for the DoctorSchedule page).
    Route::get('/doctor-schedule', [\App\Http\Controllers\V2\DoctorScheduleController::class, 'index'])->name('doctor-schedule.index');

    // Patient files (admin browse view; per-patient upload uses the JSON
    // endpoints already declared above).
    Route::get('/patient-files', [\App\Http\Controllers\V2\PatientFilesController::class, 'index'])->name('patient-files.index');

    // Insurance — Insurers (v2 replacement for Filament InsurerResource).
    Route::get('/insurance/insurers',                    [\App\Http\Controllers\V2\InsurersController::class, 'index'])->name('insurance.insurers.index');
    Route::post('/insurance/insurers',                   [\App\Http\Controllers\V2\InsurersController::class, 'store'])->name('insurance.insurers.store');
    Route::put('/insurance/insurers/{insurer}',          [\App\Http\Controllers\V2\InsurersController::class, 'update'])->name('insurance.insurers.update');
    Route::delete('/insurance/insurers/{insurer}',       [\App\Http\Controllers\V2\InsurersController::class, 'destroy'])->name('insurance.insurers.destroy');
    Route::post('/insurance/insurers/{insurer}/restore', [\App\Http\Controllers\V2\InsurersController::class, 'restore'])->name('insurance.insurers.restore');

    // Inpatient — Wards (v2).
    Route::get('/inpatient/wards',          [\App\Http\Controllers\V2\WardsController::class, 'index'])->name('inpatient.wards.index');
    Route::post('/inpatient/wards',         [\App\Http\Controllers\V2\WardsController::class, 'store'])->name('inpatient.wards.store');
    Route::put('/inpatient/wards/{ward}',   [\App\Http\Controllers\V2\WardsController::class, 'update'])->name('inpatient.wards.update');
    Route::delete('/inpatient/wards/{ward}',[\App\Http\Controllers\V2\WardsController::class, 'destroy'])->name('inpatient.wards.destroy');

    // Inpatient — Beds (v2).
    Route::get('/inpatient/beds',         [\App\Http\Controllers\V2\BedsController::class, 'index'])->name('inpatient.beds.index');
    Route::post('/inpatient/beds',        [\App\Http\Controllers\V2\BedsController::class, 'store'])->name('inpatient.beds.store');
    Route::put('/inpatient/beds/{bed}',   [\App\Http\Controllers\V2\BedsController::class, 'update'])->name('inpatient.beds.update');
    Route::delete('/inpatient/beds/{bed}',[\App\Http\Controllers\V2\BedsController::class, 'destroy'])->name('inpatient.beds.destroy');

    // Inpatient module — visual bed board + admissions workflow.
    Route::get('/inpatient/board',      [\App\Http\Controllers\V2\InpatientController::class, 'board'])->name('inpatient.board');
    Route::get('/inpatient/admissions', [\App\Http\Controllers\V2\InpatientController::class, 'admissionsIndex'])->name('inpatient.admissions');

    Route::get('/api/inpatient/admissions/{admission}',           [\App\Http\Controllers\V2\InpatientController::class, 'show'])->name('api.inpatient.admissions.show');
    Route::get('/inpatient/admissions/{admission}/print',         [\App\Http\Controllers\V2\InpatientController::class, 'printSummary'])->name('inpatient.admissions.print');
    Route::post('/api/inpatient/admit',                           [\App\Http\Controllers\V2\InpatientController::class, 'admit'])->name('api.inpatient.admit');
    Route::post('/api/inpatient/admissions/{admission}/assign-bed', [\App\Http\Controllers\V2\InpatientController::class, 'assignBed'])->name('api.inpatient.assign-bed');
    Route::post('/api/inpatient/admissions/{admission}/transfer',   [\App\Http\Controllers\V2\InpatientController::class, 'transfer'])->name('api.inpatient.transfer');
    Route::post('/api/inpatient/admissions/{admission}/discharge',  [\App\Http\Controllers\V2\InpatientController::class, 'discharge'])->name('api.inpatient.discharge');
    Route::post('/api/inpatient/admissions/{admission}/rounds',     [\App\Http\Controllers\V2\InpatientController::class, 'addRound'])->name('api.inpatient.rounds.add');
    Route::post('/api/inpatient/admissions/{admission}/charges',    [\App\Http\Controllers\V2\InpatientController::class, 'addCharge'])->name('api.inpatient.charges.add');
    Route::post('/api/inpatient/beds/{bed}/status',                 [\App\Http\Controllers\V2\InpatientController::class, 'setBedStatus'])->name('api.inpatient.beds.status');

    Route::get('/api/inpatient/lookup/patients', [\App\Http\Controllers\V2\InpatientController::class, 'lookupPatients'])->name('api.inpatient.lookup.patients');
    Route::get('/api/inpatient/lookup/doctors',  [\App\Http\Controllers\V2\InpatientController::class, 'lookupDoctors'])->name('api.inpatient.lookup.doctors');
    Route::get('/api/inpatient/lookup/branches', [\App\Http\Controllers\V2\InpatientController::class, 'lookupBranches'])->name('api.inpatient.lookup.branches');
    Route::get('/api/inpatient/lookup/available-beds', [\App\Http\Controllers\V2\InpatientController::class, 'lookupAvailableBeds'])->name('api.inpatient.lookup.available-beds');

    // Insurance — Plans (v2 replacement for Filament InsurancePlanResource).
    Route::get('/insurance/plans',            [\App\Http\Controllers\V2\InsurancePlansController::class, 'index'])->name('insurance.plans.index');
    Route::post('/insurance/plans',           [\App\Http\Controllers\V2\InsurancePlansController::class, 'store'])->name('insurance.plans.store');
    Route::put('/insurance/plans/{plan}',     [\App\Http\Controllers\V2\InsurancePlansController::class, 'update'])->name('insurance.plans.update');
    Route::delete('/insurance/plans/{plan}',  [\App\Http\Controllers\V2\InsurancePlansController::class, 'destroy'])->name('insurance.plans.destroy');

    // Insurance — Patient policies (v2 replacement for PatientInsurancePolicyResource).
    Route::get('/insurance/policies',              [\App\Http\Controllers\V2\PatientPoliciesController::class, 'index'])->name('insurance.policies.index');
    Route::get('/api/insurance/policies/lookup',   [\App\Http\Controllers\V2\PatientPoliciesController::class, 'lookup'])->name('api.insurance.policies.lookup');
    Route::post('/insurance/policies',             [\App\Http\Controllers\V2\PatientPoliciesController::class, 'store'])->name('insurance.policies.store');
    Route::put('/insurance/policies/{policy}',     [\App\Http\Controllers\V2\PatientPoliciesController::class, 'update'])->name('insurance.policies.update');
    Route::delete('/insurance/policies/{policy}',  [\App\Http\Controllers\V2\PatientPoliciesController::class, 'destroy'])->name('insurance.policies.destroy');

    // Insurance — Pre-authorizations (v2 replacement for InsurancePreauthorizationResource).
    Route::get('/insurance/preauthorizations',                      [\App\Http\Controllers\V2\PreauthorizationsController::class, 'index'])->name('insurance.preauth.index');
    Route::post('/insurance/preauthorizations',                     [\App\Http\Controllers\V2\PreauthorizationsController::class, 'store'])->name('insurance.preauth.store');
    Route::put('/insurance/preauthorizations/{preauthorization}',   [\App\Http\Controllers\V2\PreauthorizationsController::class, 'update'])->name('insurance.preauth.update');
    Route::post('/insurance/preauthorizations/{preauthorization}/decide', [\App\Http\Controllers\V2\PreauthorizationsController::class, 'decide'])->name('insurance.preauth.decide');
    Route::delete('/insurance/preauthorizations/{preauthorization}', [\App\Http\Controllers\V2\PreauthorizationsController::class, 'destroy'])->name('insurance.preauth.destroy');

    // Insurance — Claims (v2 replacement for InsuranceClaimResource). State machine via InsuranceService.
    Route::get('/insurance/claims',                       [\App\Http\Controllers\V2\ClaimsController::class, 'index'])->name('insurance.claims.index');
    Route::get('/api/insurance/claims/{claim}',           [\App\Http\Controllers\V2\ClaimsController::class, 'show'])->name('api.insurance.claims.show');
    Route::post('/insurance/claims/from-visit',           [\App\Http\Controllers\V2\ClaimsController::class, 'createFromVisit'])->name('insurance.claims.from-visit');
    Route::post('/insurance/claims/{claim}/submit',       [\App\Http\Controllers\V2\ClaimsController::class, 'submit'])->name('insurance.claims.submit');
    Route::post('/insurance/claims/{claim}/review',       [\App\Http\Controllers\V2\ClaimsController::class, 'review'])->name('insurance.claims.review');
    Route::post('/insurance/claims/{claim}/approve',      [\App\Http\Controllers\V2\ClaimsController::class, 'approve'])->name('insurance.claims.approve');
    Route::post('/insurance/claims/{claim}/partial',      [\App\Http\Controllers\V2\ClaimsController::class, 'partiallyApprove'])->name('insurance.claims.partial');
    Route::post('/insurance/claims/{claim}/reject',       [\App\Http\Controllers\V2\ClaimsController::class, 'reject'])->name('insurance.claims.reject');
    Route::post('/insurance/claims/{claim}/payment',      [\App\Http\Controllers\V2\ClaimsController::class, 'recordPayment'])->name('insurance.claims.payment');
    Route::post('/insurance/claims/{claim}/writeoff',     [\App\Http\Controllers\V2\ClaimsController::class, 'writeOff'])->name('insurance.claims.writeoff');
    Route::post('/insurance/claims/{claim}/void',         [\App\Http\Controllers\V2\ClaimsController::class, 'void'])->name('insurance.claims.void');

    // Pharmacy — Clinic items (v2 replacement for ClinicItemResource).
    Route::get('/clinic-items',                [\App\Http\Controllers\V2\ClinicItemsController::class, 'index'])->name('clinic-items.index');
    Route::post('/clinic-items',               [\App\Http\Controllers\V2\ClinicItemsController::class, 'store'])->name('clinic-items.store');
    Route::put('/clinic-items/{clinicItem}',   [\App\Http\Controllers\V2\ClinicItemsController::class, 'update'])->name('clinic-items.update');
    Route::delete('/clinic-items/{clinicItem}',[\App\Http\Controllers\V2\ClinicItemsController::class, 'destroy'])->name('clinic-items.destroy');

    // Pharmacy — Clinic stock (v2 replacement for ClinicItemStockResource).
    Route::get('/clinic-stock',                [\App\Http\Controllers\V2\ClinicStockController::class, 'index'])->name('clinic-stock.index');
    Route::post('/clinic-stock',               [\App\Http\Controllers\V2\ClinicStockController::class, 'store'])->name('clinic-stock.store');
    Route::post('/clinic-stock/receive',       [\App\Http\Controllers\V2\ClinicStockController::class, 'receive'])->name('clinic-stock.receive');
    Route::put('/clinic-stock/{stock}',        [\App\Http\Controllers\V2\ClinicStockController::class, 'update'])->name('clinic-stock.update');
    Route::delete('/clinic-stock/{stock}',     [\App\Http\Controllers\V2\ClinicStockController::class, 'destroy'])->name('clinic-stock.destroy');

    // Pharmacy — Stock movements (read-only; v2 replacement for ClinicStockMovementResource).
    Route::get('/stock-movements', [\App\Http\Controllers\V2\StockMovementsController::class, 'index'])->name('stock-movements.index');

    // Pharmacy — Inter-branch stock transfers (hub → branch dispatch).
    Route::get('/stock-transfers',  [\App\Http\Controllers\V2\StockTransfersController::class, 'index'])->name('stock-transfers.index');
    Route::post('/stock-transfers', [\App\Http\Controllers\V2\StockTransfersController::class, 'store'])->name('stock-transfers.store');
    Route::post('/stock-transfers/{transfer}/dispatch', [\App\Http\Controllers\V2\StockTransfersController::class, 'dispatchTransfer'])->name('stock-transfers.dispatch');
    Route::post('/stock-transfers/{transfer}/cancel',   [\App\Http\Controllers\V2\StockTransfersController::class, 'cancel'])->name('stock-transfers.cancel');

    // Pharmacy — Visit stock requests (v2 replacement for VisitStockRequestResource).
    Route::get('/visit-stock-requests', [\App\Http\Controllers\V2\VisitStockRequestsController::class, 'index'])->name('visit-stock-requests.index');
    Route::post('/visit-stock-requests/{visitStockRequest}/fulfill', [\App\Http\Controllers\V2\VisitStockRequestsController::class, 'fulfill'])->name('visit-stock-requests.fulfill');
    Route::post('/visit-stock-requests/{visitStockRequest}/cancel',  [\App\Http\Controllers\V2\VisitStockRequestsController::class, 'cancel'])->name('visit-stock-requests.cancel');

    // Setup — Clinic packages catalog (v2 replacement for ClinicPackageResource).
    Route::get('/clinic-packages',                 [\App\Http\Controllers\V2\ClinicPackagesController::class, 'index'])->name('clinic-packages.index');
    Route::post('/clinic-packages',                [\App\Http\Controllers\V2\ClinicPackagesController::class, 'store'])->name('clinic-packages.store');
    Route::put('/clinic-packages/{clinicPackage}', [\App\Http\Controllers\V2\ClinicPackagesController::class, 'update'])->name('clinic-packages.update');
    Route::delete('/clinic-packages/{clinicPackage}', [\App\Http\Controllers\V2\ClinicPackagesController::class, 'destroy'])->name('clinic-packages.destroy');

    // Billing — Clinic coupons (codes applied to a visit at checkout). Admin/clinic-admin.
    Route::get('/coupons',                  [\App\Http\Controllers\V2\ClinicCouponsController::class, 'index'])->name('coupons.index');
    Route::post('/coupons',                 [\App\Http\Controllers\V2\ClinicCouponsController::class, 'store'])->name('coupons.store');
    Route::put('/coupons/{clinicCoupon}',   [\App\Http\Controllers\V2\ClinicCouponsController::class, 'update'])->name('coupons.update');
    Route::delete('/coupons/{clinicCoupon}', [\App\Http\Controllers\V2\ClinicCouponsController::class, 'destroy'])->name('coupons.destroy');

    // Billing — Time-bound catalog promotions (auto-discount items/services).
    Route::get('/promotions',                    [\App\Http\Controllers\V2\ClinicPromotionsController::class, 'index'])->name('promotions.index');
    Route::post('/promotions',                   [\App\Http\Controllers\V2\ClinicPromotionsController::class, 'store'])->name('promotions.store');
    Route::put('/promotions/{clinicPromotion}',  [\App\Http\Controllers\V2\ClinicPromotionsController::class, 'update'])->name('promotions.update');
    Route::delete('/promotions/{clinicPromotion}', [\App\Http\Controllers\V2\ClinicPromotionsController::class, 'destroy'])->name('promotions.destroy');

    // Compliance — Follow-up plans (read-only; v2 replacement for FollowUpPlanResource).
    Route::get('/follow-up-plans', [\App\Http\Controllers\V2\FollowUpPlansController::class, 'index'])->name('follow-up-plans.index');

    // HR — Doctor compensation ledger (read-only earnings).
    Route::get('/doctor-compensation', [\App\Http\Controllers\V2\DoctorCompLedgerController::class, 'index'])->name('doctor-compensation.index');

    // HR — Doctor compensation profiles (rate config CRUD).
    Route::get('/doctor-compensation-profiles',           [\App\Http\Controllers\V2\DoctorCompProfilesController::class, 'index'])->name('doctor-compensation-profiles.index');
    Route::post('/doctor-compensation-profiles',          [\App\Http\Controllers\V2\DoctorCompProfilesController::class, 'store'])->name('doctor-compensation-profiles.store');
    Route::put('/doctor-compensation-profiles/{profile}', [\App\Http\Controllers\V2\DoctorCompProfilesController::class, 'update'])->name('doctor-compensation-profiles.update');
    Route::delete('/doctor-compensation-profiles/{profile}', [\App\Http\Controllers\V2\DoctorCompProfilesController::class, 'destroy'])->name('doctor-compensation-profiles.destroy');

    // Platform — Activity log (read-only; admin only).
    Route::get('/activity-log', [\App\Http\Controllers\V2\ActivityLogController::class, 'index'])->name('activity-log.index');

    // Accounting — Chart of Accounts (v2 replacement for ChartOfAccountResource).
    Route::get('/accounting/chart-of-accounts',           [\App\Http\Controllers\V2\ChartOfAccountsController::class, 'index'])->name('accounting.accounts.index');
    Route::post('/accounting/chart-of-accounts',          [\App\Http\Controllers\V2\ChartOfAccountsController::class, 'store'])->name('accounting.accounts.store');
    Route::put('/accounting/chart-of-accounts/{account}', [\App\Http\Controllers\V2\ChartOfAccountsController::class, 'update'])->name('accounting.accounts.update');
    Route::delete('/accounting/chart-of-accounts/{account}', [\App\Http\Controllers\V2\ChartOfAccountsController::class, 'destroy'])->name('accounting.accounts.destroy');

    // Accounting — Expenses (v2 replacement for ExpenseResource).
    Route::get('/accounting/expenses',                 [\App\Http\Controllers\V2\ExpensesController::class, 'index'])->name('accounting.expenses.index');
    Route::post('/accounting/expenses',                [\App\Http\Controllers\V2\ExpensesController::class, 'store'])->name('accounting.expenses.store');
    Route::put('/accounting/expenses/{expense}',       [\App\Http\Controllers\V2\ExpensesController::class, 'update'])->name('accounting.expenses.update');
    Route::post('/accounting/expenses/{expense}/post', [\App\Http\Controllers\V2\ExpensesController::class, 'post'])->name('accounting.expenses.post');
    Route::post('/accounting/expenses/{expense}/void', [\App\Http\Controllers\V2\ExpensesController::class, 'void'])->name('accounting.expenses.void');
    Route::delete('/accounting/expenses/{expense}',    [\App\Http\Controllers\V2\ExpensesController::class, 'destroy'])->name('accounting.expenses.destroy');

    // Accounting — Journal Entries (v2 replacement for JournalEntryResource).
    Route::get('/accounting/journal-entries',                      [\App\Http\Controllers\V2\JournalEntriesController::class, 'index'])->name('accounting.journal-entries.index');
    Route::get('/api/accounting/journal-entries/{journalEntry}',   [\App\Http\Controllers\V2\JournalEntriesController::class, 'show'])->name('api.accounting.journal-entries.show');
    Route::post('/accounting/journal-entries',                     [\App\Http\Controllers\V2\JournalEntriesController::class, 'store'])->name('accounting.journal-entries.store');
    Route::put('/accounting/journal-entries/{journalEntry}',       [\App\Http\Controllers\V2\JournalEntriesController::class, 'update'])->name('accounting.journal-entries.update');
    Route::post('/accounting/journal-entries/{journalEntry}/post', [\App\Http\Controllers\V2\JournalEntriesController::class, 'post'])->name('accounting.journal-entries.post');
    Route::post('/accounting/journal-entries/{journalEntry}/reverse', [\App\Http\Controllers\V2\JournalEntriesController::class, 'reverse'])->name('accounting.journal-entries.reverse');
    Route::delete('/accounting/journal-entries/{journalEntry}',    [\App\Http\Controllers\V2\JournalEntriesController::class, 'destroy'])->name('accounting.journal-entries.destroy');

    // Accounting — Bank Reconciliation (v2 replacement for BankReconciliationResource).
    Route::get('/accounting/bank-reconciliations',                          [\App\Http\Controllers\V2\BankReconciliationController::class, 'index'])->name('accounting.bank-rec.index');
    Route::get('/api/accounting/bank-reconciliations/{bankReconciliation}', [\App\Http\Controllers\V2\BankReconciliationController::class, 'show'])->name('api.accounting.bank-rec.show');
    Route::post('/accounting/bank-reconciliations',                         [\App\Http\Controllers\V2\BankReconciliationController::class, 'store'])->name('accounting.bank-rec.store');
    Route::put('/accounting/bank-reconciliations/{bankReconciliation}',     [\App\Http\Controllers\V2\BankReconciliationController::class, 'update'])->name('accounting.bank-rec.update');
    Route::post('/accounting/bank-reconciliations/{bankReconciliation}/recompute',  [\App\Http\Controllers\V2\BankReconciliationController::class, 'recompute'])->name('accounting.bank-rec.recompute');
    Route::post('/accounting/bank-reconciliations/{bankReconciliation}/auto-match', [\App\Http\Controllers\V2\BankReconciliationController::class, 'autoMatch'])->name('accounting.bank-rec.auto-match');
    Route::post('/accounting/bank-reconciliations/{bankReconciliation}/complete',   [\App\Http\Controllers\V2\BankReconciliationController::class, 'complete'])->name('accounting.bank-rec.complete');
    Route::post('/accounting/bank-reconciliations/{bankReconciliation}/reopen',     [\App\Http\Controllers\V2\BankReconciliationController::class, 'reopen'])->name('accounting.bank-rec.reopen');
    Route::post('/accounting/bank-reconciliations/{bankReconciliation}/import',     [\App\Http\Controllers\V2\BankReconciliationController::class, 'importCsv'])->name('accounting.bank-rec.import');
    Route::post('/accounting/bank-statement-lines/{line}/match',   [\App\Http\Controllers\V2\BankReconciliationController::class, 'matchLine'])->name('accounting.bank-rec.match');
    Route::post('/accounting/bank-statement-lines/{line}/unmatch', [\App\Http\Controllers\V2\BankReconciliationController::class, 'unmatchLine'])->name('accounting.bank-rec.unmatch');

    // Accounting — Vendors (v2 replacement for Accounting\VendorResource).
    Route::get('/accounting/vendors',           [\App\Http\Controllers\V2\VendorsController::class, 'index'])->name('accounting.vendors.index');
    Route::post('/accounting/vendors',          [\App\Http\Controllers\V2\VendorsController::class, 'store'])->name('accounting.vendors.store');
    Route::put('/accounting/vendors/{vendor}',  [\App\Http\Controllers\V2\VendorsController::class, 'update'])->name('accounting.vendors.update');
    Route::delete('/accounting/vendors/{vendor}', [\App\Http\Controllers\V2\VendorsController::class, 'destroy'])->name('accounting.vendors.destroy');

    // Accounting — Periods (v2 replacement for Accounting\AccountingPeriodResource). Read-only + close/reopen.
    Route::get('/accounting/periods',                  [\App\Http\Controllers\V2\AccountingPeriodsController::class, 'index'])->name('accounting.periods.index');
    Route::post('/accounting/periods/{period}/close',  [\App\Http\Controllers\V2\AccountingPeriodsController::class, 'close'])->name('accounting.periods.close');
    Route::post('/accounting/periods/{period}/reopen', [\App\Http\Controllers\V2\AccountingPeriodsController::class, 'reopen'])->name('accounting.periods.reopen');

    // Accounting — Financial statements (v2 replacement for the 5 Filament report pages).
    Route::get('/reports/accounting/trial-balance',  [\App\Http\Controllers\V2\AccountingReportsController::class, 'trialBalance'])->name('reports.accounting.trial-balance');
    Route::get('/reports/accounting/general-ledger', [\App\Http\Controllers\V2\AccountingReportsController::class, 'generalLedger'])->name('reports.accounting.general-ledger');
    Route::get('/reports/accounting/profit-loss',    [\App\Http\Controllers\V2\AccountingReportsController::class, 'profitAndLoss'])->name('reports.accounting.profit-loss');
    Route::get('/reports/accounting/balance-sheet',  [\App\Http\Controllers\V2\AccountingReportsController::class, 'balanceSheet'])->name('reports.accounting.balance-sheet');
    Route::get('/reports/accounting/cash-flow',      [\App\Http\Controllers\V2\AccountingReportsController::class, 'cashFlow'])->name('reports.accounting.cash-flow');

    // Setup — Clinics (v2 replacement for PartnerResource). Admin only.
    Route::get('/partners',            [\App\Http\Controllers\V2\PartnersController::class, 'index'])->name('partners.index');
    Route::post('/partners',           [\App\Http\Controllers\V2\PartnersController::class, 'store'])->name('partners.store');
    Route::put('/partners/{partner}',  [\App\Http\Controllers\V2\PartnersController::class, 'update'])->name('partners.update');
    Route::delete('/partners/{partner}', [\App\Http\Controllers\V2\PartnersController::class, 'destroy'])->name('partners.destroy');

    // Setup — Gateway accounts (v2 replacement for GatewayAccountResource). Admin only.
    Route::get('/gateway-accounts',                    [\App\Http\Controllers\V2\GatewayAccountsController::class, 'index'])->name('gateway-accounts.index');
    Route::post('/gateway-accounts',                   [\App\Http\Controllers\V2\GatewayAccountsController::class, 'store'])->name('gateway-accounts.store');
    Route::put('/gateway-accounts/{gatewayAccount}',   [\App\Http\Controllers\V2\GatewayAccountsController::class, 'update'])->name('gateway-accounts.update');
    Route::delete('/gateway-accounts/{gatewayAccount}', [\App\Http\Controllers\V2\GatewayAccountsController::class, 'destroy'])->name('gateway-accounts.destroy');

    // WhatsApp — Commands (v2 replacement for WACommandResource). Admin only.
    Route::get('/whatsapp/commands',               [\App\Http\Controllers\V2\WaCommandsController::class, 'index'])->name('whatsapp.commands.index');
    Route::post('/whatsapp/commands',              [\App\Http\Controllers\V2\WaCommandsController::class, 'store'])->name('whatsapp.commands.store');
    Route::put('/whatsapp/commands/{waCommand}',   [\App\Http\Controllers\V2\WaCommandsController::class, 'update'])->name('whatsapp.commands.update');
    Route::delete('/whatsapp/commands/{waCommand}', [\App\Http\Controllers\V2\WaCommandsController::class, 'destroy'])->name('whatsapp.commands.destroy');

    // WhatsApp — Message Catalog (MessageText). Admin only.
    Route::get('/whatsapp/message-texts',                [\App\Http\Controllers\V2\MessageTextsController::class, 'index'])->name('whatsapp.message-texts.index');
    Route::post('/whatsapp/message-texts',               [\App\Http\Controllers\V2\MessageTextsController::class, 'store'])->name('whatsapp.message-texts.store');
    Route::put('/whatsapp/message-texts/{messageText}',  [\App\Http\Controllers\V2\MessageTextsController::class, 'update'])->name('whatsapp.message-texts.update');
    Route::delete('/whatsapp/message-texts/{messageText}', [\App\Http\Controllers\V2\MessageTextsController::class, 'destroy'])->name('whatsapp.message-texts.destroy');

    // WhatsApp — Templates (WAMessage). Admin only.
    Route::get('/whatsapp/messages',               [\App\Http\Controllers\V2\WaMessagesController::class, 'index'])->name('whatsapp.messages.index');
    Route::post('/whatsapp/messages',              [\App\Http\Controllers\V2\WaMessagesController::class, 'store'])->name('whatsapp.messages.store');
    Route::put('/whatsapp/messages/{waMessage}',   [\App\Http\Controllers\V2\WaMessagesController::class, 'update'])->name('whatsapp.messages.update');
    Route::delete('/whatsapp/messages/{waMessage}', [\App\Http\Controllers\V2\WaMessagesController::class, 'destroy'])->name('whatsapp.messages.destroy');

    // WhatsApp — monitoring (read-only): logs, sessions, audience metrics. Admin only.
    Route::get('/whatsapp/logs',              [\App\Http\Controllers\V2\WaLogsController::class, 'logs'])->name('whatsapp.logs');
    Route::get('/whatsapp/sessions',          [\App\Http\Controllers\V2\WaLogsController::class, 'sessions'])->name('whatsapp.sessions');
    Route::get('/whatsapp/audience-metrics',  [\App\Http\Controllers\V2\AudienceMetricsController::class, 'index'])->name('whatsapp.audience-metrics.index');

    // WhatsApp Platform (isolated app/Wa module) — native v2 screens driving the
    // module's own data on the `wa` connection. Distinct from the clinic WhatsApp
    // routes above. Names: v2.wa-module.*
    Route::get('/wa-module',                       [\App\Http\Controllers\V2\WaModuleController::class, 'dashboard'])->name('wa-module.dashboard');
    Route::get('/wa-module/templates',             [\App\Http\Controllers\V2\WaModuleController::class, 'templates'])->name('wa-module.templates');
    Route::get('/wa-module/contacts',              [\App\Http\Controllers\V2\WaModuleController::class, 'contacts'])->name('wa-module.contacts');
    Route::get('/wa-module/campaigns',             [\App\Http\Controllers\V2\WaModuleController::class, 'campaigns'])->name('wa-module.campaigns');
    Route::get('/wa-module/inbox',                 [\App\Http\Controllers\V2\WaModuleController::class, 'inbox'])->name('wa-module.inbox');
    Route::get('/wa-module/conversations',         [\App\Http\Controllers\V2\WaModuleController::class, 'conversations'])->name('wa-module.conversations');
    Route::get('/wa-module/conversations/{conversation}', [\App\Http\Controllers\V2\WaModuleController::class, 'conversation'])->name('wa-module.conversation');
    Route::get('/wa-module/logs',                  [\App\Http\Controllers\V2\WaModuleController::class, 'logs'])->name('wa-module.logs');
    Route::get('/wa-module/sessions',              [\App\Http\Controllers\V2\WaModuleController::class, 'sessions'])->name('wa-module.sessions');
    Route::post('/wa-module/send',                 [\App\Http\Controllers\V2\WaModuleController::class, 'sendMessage'])->name('wa-module.send');
    // Templates CRUD + Meta actions
    Route::post('/wa-module/templates',                       [\App\Http\Controllers\V2\WaModuleController::class, 'storeTemplate'])->name('wa-module.templates.store');
    Route::post('/wa-module/templates-carousel',              [\App\Http\Controllers\V2\WaModuleController::class, 'storeCarousel'])->name('wa-module.templates.carousel');
    Route::put('/wa-module/templates/{template}',             [\App\Http\Controllers\V2\WaModuleController::class, 'updateTemplate'])->name('wa-module.templates.update');
    Route::delete('/wa-module/templates/{template}',          [\App\Http\Controllers\V2\WaModuleController::class, 'destroyTemplate'])->name('wa-module.templates.destroy');
    Route::post('/wa-module/templates-sync',                  [\App\Http\Controllers\V2\WaModuleController::class, 'syncTemplates'])->name('wa-module.templates.sync');
    Route::post('/wa-module/templates/{template}/publish',    [\App\Http\Controllers\V2\WaModuleController::class, 'publishTemplate'])->name('wa-module.templates.publish');
    Route::post('/wa-module/templates/{template}/auto-reply', [\App\Http\Controllers\V2\WaModuleController::class, 'toggleTemplateAutoReply'])->name('wa-module.templates.auto-reply');
    // Contacts + Groups CRUD
    Route::post('/wa-module/contacts',              [\App\Http\Controllers\V2\WaModuleController::class, 'storeContact'])->name('wa-module.contacts.store');
    Route::put('/wa-module/contacts/{contact}',     [\App\Http\Controllers\V2\WaModuleController::class, 'updateContact'])->name('wa-module.contacts.update');
    Route::delete('/wa-module/contacts/{contact}',  [\App\Http\Controllers\V2\WaModuleController::class, 'destroyContact'])->name('wa-module.contacts.destroy');
    Route::post('/wa-module/groups',                [\App\Http\Controllers\V2\WaModuleController::class, 'storeGroup'])->name('wa-module.groups.store');
    Route::delete('/wa-module/groups/{group}',      [\App\Http\Controllers\V2\WaModuleController::class, 'destroyGroup'])->name('wa-module.groups.destroy');
    Route::post('/wa-module/groups/{group}/toggle', [\App\Http\Controllers\V2\WaModuleController::class, 'toggleGroupMember'])->name('wa-module.groups.toggle');
    // Sessions + conversation reply
    Route::post('/wa-module/sessions/{session}/block',  [\App\Http\Controllers\V2\WaModuleController::class, 'toggleSessionBlock'])->name('wa-module.sessions.block');
    Route::delete('/wa-module/sessions/{session}',      [\App\Http\Controllers\V2\WaModuleController::class, 'destroySession'])->name('wa-module.sessions.destroy');
    Route::post('/wa-module/conversations/{conversation}/reply', [\App\Http\Controllers\V2\WaModuleController::class, 'replyConversation'])->name('wa-module.conversations.reply');
    Route::post('/wa-module/conversations/{conversation}/template', [\App\Http\Controllers\V2\WaModuleController::class, 'sendConversationTemplate'])->name('wa-module.conversations.template');
    Route::post('/wa-module/inbox/start',  [\App\Http\Controllers\V2\WaModuleController::class, 'startChat'])->name('wa-module.inbox.start');
    Route::post('/wa-module/connect',      [\App\Http\Controllers\V2\WaModuleController::class, 'connectNumber'])->name('wa-module.connect');
    // Campaigns CRUD + send + recipients
    Route::post('/wa-module/campaigns',                       [\App\Http\Controllers\V2\WaModuleController::class, 'storeCampaign'])->name('wa-module.campaigns.store');
    Route::put('/wa-module/campaigns/{campaign}',             [\App\Http\Controllers\V2\WaModuleController::class, 'updateCampaign'])->name('wa-module.campaigns.update');
    Route::delete('/wa-module/campaigns/{campaign}',          [\App\Http\Controllers\V2\WaModuleController::class, 'destroyCampaign'])->name('wa-module.campaigns.destroy');
    Route::post('/wa-module/campaigns/{campaign}/send',       [\App\Http\Controllers\V2\WaModuleController::class, 'sendCampaign'])->name('wa-module.campaigns.send');
    Route::post('/wa-module/campaigns/{campaign}/recipients', [\App\Http\Controllers\V2\WaModuleController::class, 'addCampaignRecipient'])->name('wa-module.campaigns.recipients');
    Route::post('/wa-module/campaigns/{campaign}/import',     [\App\Http\Controllers\V2\WaModuleController::class, 'importRecipients'])->name('wa-module.campaigns.import');
    Route::post('/wa-module/campaigns/{campaign}/from-group',  [\App\Http\Controllers\V2\WaModuleController::class, 'importFromGroup'])->name('wa-module.campaigns.from-group');
    Route::get('/wa-module/campaigns/{campaign}/analytics',    [\App\Http\Controllers\V2\WaModuleController::class, 'campaignAnalytics'])->name('wa-module.campaigns.analytics');
    // Engagement + smart groups
    Route::post('/wa-module/engagement/refresh', [\App\Http\Controllers\V2\WaModuleController::class, 'refreshEngagement'])->name('wa-module.engagement.refresh');
    Route::post('/wa-module/groups/smart',       [\App\Http\Controllers\V2\WaModuleController::class, 'buildSmartGroup'])->name('wa-module.groups.smart');
    // Audience builder + contact import/export
    Route::get('/wa-module/audience',            [\App\Http\Controllers\V2\WaModuleController::class, 'audience'])->name('wa-module.audience');
    Route::post('/wa-module/audience/to-group',  [\App\Http\Controllers\V2\WaModuleController::class, 'audienceToGroup'])->name('wa-module.audience.to-group');
    Route::get('/wa-module/contacts/export',     [\App\Http\Controllers\V2\WaModuleController::class, 'exportContacts'])->name('wa-module.contacts.export');
    Route::post('/wa-module/contacts/import',    [\App\Http\Controllers\V2\WaModuleController::class, 'importContacts'])->name('wa-module.contacts.import');
    // Settings
    Route::get('/wa-module/settings',  [\App\Http\Controllers\V2\WaModuleController::class, 'settings'])->name('wa-module.settings');
    Route::post('/wa-module/settings', [\App\Http\Controllers\V2\WaModuleController::class, 'updateSettings'])->name('wa-module.settings.update');

    // WhatsApp — Triggers (auto-reply builder, v2 replacement for WhatsappTriggerResource). Admin only.
    Route::get('/whatsapp/triggers',                [\App\Http\Controllers\V2\WhatsappTriggersController::class, 'index'])->name('whatsapp.triggers.index');
    Route::post('/whatsapp/triggers',               [\App\Http\Controllers\V2\WhatsappTriggersController::class, 'store'])->name('whatsapp.triggers.store');
    Route::put('/whatsapp/triggers/{whatsappTrigger}',    [\App\Http\Controllers\V2\WhatsappTriggersController::class, 'update'])->name('whatsapp.triggers.update');
    Route::delete('/whatsapp/triggers/{whatsappTrigger}', [\App\Http\Controllers\V2\WhatsappTriggersController::class, 'destroy'])->name('whatsapp.triggers.destroy');

    // WhatsApp — Bulk Invite Campaigns (v2 replacement for BulkInviteCampaignResource). Admin only.
    Route::get('/campaigns',                  [\App\Http\Controllers\V2\CampaignsController::class, 'index'])->name('campaigns.index');
    Route::get('/campaigns/{campaign}',       [\App\Http\Controllers\V2\CampaignsController::class, 'show'])->name('campaigns.show');
    Route::post('/campaigns',                 [\App\Http\Controllers\V2\CampaignsController::class, 'store'])->name('campaigns.store');
    Route::put('/campaigns/{campaign}',       [\App\Http\Controllers\V2\CampaignsController::class, 'update'])->name('campaigns.update');
    Route::delete('/campaigns/{campaign}',    [\App\Http\Controllers\V2\CampaignsController::class, 'destroy'])->name('campaigns.destroy');
    Route::post('/campaigns/{campaign}/recipients',                 [\App\Http\Controllers\V2\CampaignsController::class, 'addRecipients'])->name('campaigns.recipients.add');
    Route::delete('/campaigns/{campaign}/recipients/{recipient}',   [\App\Http\Controllers\V2\CampaignsController::class, 'deleteRecipient'])->name('campaigns.recipients.delete');
    Route::post('/campaigns/{campaign}/test',  [\App\Http\Controllers\V2\CampaignsController::class, 'sendTest'])->name('campaigns.test');
    Route::post('/campaigns/{campaign}/queue', [\App\Http\Controllers\V2\CampaignsController::class, 'queue'])->name('campaigns.queue');

    // Visits list (v2 replacement for the VisitResource table; rows open the v2 console).
    Route::get('/visits-list',                [\App\Http\Controllers\V2\VisitsController::class, 'index'])->name('visits.index');
    Route::post('/visits/{visit}/recompute',  [\App\Http\Controllers\V2\VisitsController::class, 'recompute'])->name('visits.recompute');

    // Platform — System settings (v2 replacement for SystemSettingResource).
    Route::get('/settings',  [\App\Http\Controllers\V2\SystemSettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings',  [\App\Http\Controllers\V2\SystemSettingsController::class, 'update'])->name('settings.update');

    // Reports (v2 replacements for the Filament report pages).
    Route::get('/reports',                [\App\Http\Controllers\V2\ClinicReportsController::class, 'index'])->name('reports.clinic');
    Route::get('/reports/daily-closing',  [\App\Http\Controllers\V2\DailyClosingController::class, 'index'])->name('reports.daily-closing');
    Route::get('/reports/daily-reconciliation', [\App\Http\Controllers\V2\DailyReconciliationController::class, 'index'])->name('reports.daily-reconciliation');
    Route::get('/reports/executive',      [\App\Http\Controllers\V2\ExecutiveDashboardController::class, 'index'])->name('reports.executive');
    Route::get('/inpatient/reports',      [\App\Http\Controllers\V2\InpatientReportsController::class, 'index'])->name('inpatient.reports');
    // Doctor self-service: my own daily earnings, for end-of-day closing.
    Route::get('/my-earnings',            [\App\Http\Controllers\V2\MyEarningsController::class, 'index'])->name('my-earnings');

    // Styled .xlsx exports (plain file downloads, not Inertia). Collision-free paths.
    // Each mirrors its list's current filters; selection-capable lists also pass ids.
    Route::get('/exports/patients', [\App\Http\Controllers\V2\PatientsController::class, 'export'])->name('patients.export');
    Route::get('/exports/visits',   [\App\Http\Controllers\V2\VisitsController::class, 'export'])->name('visits.export');
    Route::get('/exports/bookings', [\App\Http\Controllers\V2\BookingsController::class, 'export'])->name('bookings.export');
    Route::get('/exports/claims',   [\App\Http\Controllers\V2\ClaimsController::class, 'export'])->name('insurance.claims.export');
    Route::get('/exports/doctors',           [\App\Http\Controllers\V2\DoctorsController::class, 'export'])->name('doctors.export');
    Route::get('/exports/insurers',          [\App\Http\Controllers\V2\InsurersController::class, 'export'])->name('insurance.insurers.export');
    Route::get('/exports/expenses',          [\App\Http\Controllers\V2\ExpensesController::class, 'export'])->name('accounting.expenses.export');
    Route::get('/exports/journal-entries',   [\App\Http\Controllers\V2\JournalEntriesController::class, 'export'])->name('accounting.journal-entries.export');
    Route::get('/exports/doctor-compensation', [\App\Http\Controllers\V2\DoctorCompLedgerController::class, 'export'])->name('doctor-compensation.export');
    Route::get('/exports/stock-movements',   [\App\Http\Controllers\V2\StockMovementsController::class, 'export'])->name('stock-movements.export');
    Route::get('/exports/clinic-stock',      [\App\Http\Controllers\V2\ClinicStockController::class, 'export'])->name('clinic-stock.export');
    Route::get('/exports/preauthorizations', [\App\Http\Controllers\V2\PreauthorizationsController::class, 'export'])->name('insurance.preauth.export');
    Route::get('/exports/patient-policies',  [\App\Http\Controllers\V2\PatientPoliciesController::class, 'export'])->name('insurance.policies.export');
    Route::get('/exports/staff-attendances', [\App\Http\Controllers\V2\StaffAttendancesController::class, 'export'])->name('staff-attendances.export');
    Route::get('/exports/staff-leaves',      [\App\Http\Controllers\V2\StaffLeavesController::class, 'export'])->name('staff-leaves.export');
    Route::get('/exports/admissions',        [\App\Http\Controllers\V2\InpatientController::class, 'admissionsExport'])->name('inpatient.admissions.export');

    // Tier C exports (catalogs, directories, comms logs).
    Route::get('/exports/clinic-items',      [\App\Http\Controllers\V2\ClinicItemsController::class, 'export'])->name('clinic-items.export');
    Route::get('/exports/clinic-packages',   [\App\Http\Controllers\V2\ClinicPackagesController::class, 'export'])->name('clinic-packages.export');
    Route::get('/exports/insurance-plans',   [\App\Http\Controllers\V2\InsurancePlansController::class, 'export'])->name('insurance.plans.export');
    Route::get('/exports/users',             [\App\Http\Controllers\V2\UsersController::class, 'export'])->name('users.export');
    Route::get('/exports/doctor-comp-profiles', [\App\Http\Controllers\V2\DoctorCompProfilesController::class, 'export'])->name('doctor-compensation-profiles.export');
    Route::get('/exports/follow-up-plans',   [\App\Http\Controllers\V2\FollowUpPlansController::class, 'export'])->name('follow-up-plans.export');
    Route::get('/exports/visit-stock-requests', [\App\Http\Controllers\V2\VisitStockRequestsController::class, 'export'])->name('visit-stock-requests.export');
    Route::get('/exports/patient-files',     [\App\Http\Controllers\V2\PatientFilesController::class, 'export'])->name('patient-files.export');
    Route::get('/exports/partners',          [\App\Http\Controllers\V2\PartnersController::class, 'export'])->name('partners.export');
    Route::get('/exports/wa-logs',           [\App\Http\Controllers\V2\WaLogsController::class, 'export'])->name('whatsapp.logs.export');
    Route::get('/exports/wa-messages',       [\App\Http\Controllers\V2\WaMessagesController::class, 'export'])->name('whatsapp.messages.export');
    Route::get('/exports/audience-metrics',  [\App\Http\Controllers\V2\AudienceMetricsController::class, 'export'])->name('whatsapp.audience-metrics.export');
    Route::get('/exports/vendors',           [\App\Http\Controllers\V2\VendorsController::class, 'export'])->name('accounting.vendors.export');
    Route::get('/exports/lab-tests',         [\App\Http\Controllers\V2\LabTestsController::class, 'export'])->name('lab-tests.export');

    // Excel imports for master/reference tables. {type} resolves via ImportRegistry.
    Route::get('/imports/{type}/template', [\App\Http\Controllers\V2\ImportController::class, 'template'])->name('imports.template');
    Route::post('/imports/{type}',         [\App\Http\Controllers\V2\ImportController::class, 'store'])->name('imports.store');

    // Bulk archive / restore for insurers (soft delete + undo).
    Route::post('/insurance/insurers/bulk-archive', [\App\Http\Controllers\V2\InsurersController::class, 'bulkDestroy'])->name('insurance.insurers.bulk-archive');
    Route::post('/insurance/insurers/bulk-restore', [\App\Http\Controllers\V2\InsurersController::class, 'bulkRestore'])->name('insurance.insurers.bulk-restore');

    // Bulk archive / restore for doctors (soft delete + undo).
    Route::post('/doctors/bulk-archive', [\App\Http\Controllers\V2\DoctorsController::class, 'bulkDestroy'])->name('doctors.bulk-archive');
    Route::post('/doctors/bulk-restore', [\App\Http\Controllers\V2\DoctorsController::class, 'bulkRestore'])->name('doctors.bulk-restore');
});
