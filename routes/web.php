<?php

use App\Http\Controllers\Front\Account\AddressController;
use App\Http\Controllers\Front\Account\ProfileController;
// Route::get('/', function (Request $request) {
//     $qs = $request->getQueryString();
//     return redirect('/en' . ($qs ? ('?' . $qs) : ''), 302);
// });

// ----- FRONT CONTROLLERS -----
use App\Http\Controllers\Front\Auth\LoginController;
use App\Http\Controllers\Front\Auth\LogoutController;
use App\Http\Controllers\Front\Auth\RegisterController;
use App\Http\Controllers\Front\BranchMenuController;
use App\Http\Controllers\Front\CartController;
use App\Http\Controllers\Front\CartCouponController;
use App\Http\Controllers\Front\CheckoutController;
use App\Http\Controllers\Front\CheckoutStateController;
use App\Http\Controllers\Front\GeoController;
use App\Http\Controllers\Front\HomeController;
use App\Http\Controllers\Front\LocationController;
use App\Http\Controllers\Front\OrderController;
use App\Http\Controllers\Front\PaymentController;
use App\Http\Controllers\Front\ServiceBrowseController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ---------- LANGUAGE ----------
Route::get('/language/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en']), 404);
    session()->put('lang', $locale);

    return back();
})->name('language.switch');

// All front routes go through the default "web" stack.
// Your SetLocaleFromSession middleware is appended to the web group in bootstrap/app.php

// ---------- PUBLIC BROWSE (guest + auth) ----------
Route::middleware(['web'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
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

// ---------- AUTH (guest-only pages) ----------
Route::middleware(['web', 'guest.front'])->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.attempt');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->name('register.store');
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
