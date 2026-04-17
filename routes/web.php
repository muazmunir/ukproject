<?php

use App\Http\Controllers\Coach\CoachSettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Auth\StaffPasswordResetController;
use App\Http\Controllers\Admin\AdminTransactionController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Admin\ManagerRefundAnalyticsController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use App\Http\Controllers\PublicCoachController;
use App\Http\Controllers\Admin\AdminClientAnalyticsController;
use App\Http\Controllers\Admin\SupportReviewAnalyticsController;
use App\Http\Controllers\SuperAdmin\SuperClientAnalytics;
use App\Http\Controllers\SuperAdmin\SuperWithdrawalController;
use App\Http\Controllers\Admin\AdminWithdrawalController;
use App\Http\Controllers\SuperAdmin\SuperCoachAnalytics;
use App\Http\Controllers\Client\ReservationReviewController;
use App\Http\Controllers\Admin\AdminWebAuthnController;
use App\Http\Controllers\ServiceFavoriteController;
use App\Http\Controllers\FavoritesController;
use App\Http\Controllers\ReservationSlotSessionController;
use App\Http\Controllers\Admin\AdminCoachAnalyticsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Client\RefundChoiceController;
use App\Http\Controllers\Coach\CoachWithdrawController;
use App\Http\Controllers\Admin\AdminLockedController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\Auth\StaffInviteController;
use App\Http\Controllers\Admin\AgentDmController;
use App\Http\Controllers\Admin\ManagerDmController;
// use App\Http\Controllers\Coach\CoachStripeConnectController;
use App\Http\Controllers\SuperAdmin\SuperStaffChatController;
use App\Http\Controllers\SuperAdmin\SuperStaffStatusAnalyticsController;

use App\Http\Controllers\Admin\SupportLeaveController;
use App\Http\Controllers\SuperAdmin\SuperSupportLeaveController;

use App\Http\Controllers\Admin\SupportAgentStatusAnalyticsController;
use App\Http\Controllers\SuperAdmin\SuperDisputeController;

// use App\Http\Controllers\Client\HomeController;
// use App\Http\Controllers\Client\BookingController;
use App\Http\Controllers\SuperAdmin\AuthController;
use App\Http\Controllers\Auth\OnboardingController;
use App\Http\Controllers\SlotCallController;

use App\Http\Controllers\SuperAdmin\StaffTeamController;
use App\Http\Controllers\SuperAdmin\SuperAnalyticsController;

use App\Http\Controllers\Admin\StaffChatController;
// Controllers
use App\Http\Controllers\Admin\ServiceController as AdminServiceController;
use App\Http\Controllers\Admin\AdminDisputeController;
use App\Http\Controllers\Admin\AdminProfileController;
use App\Http\Controllers\Admin\NewsletterSubscriberController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ServicesController;
// use App\Http\Controllers\Coach\CoachKycController;


use App\Http\Controllers\Coach\CoachApplicationController;
use App\Http\Controllers\Coach\CoachPayoutController;
use App\Http\Controllers\CountryCityController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Client\DashboardController;
use App\Http\Controllers\Client\ClientProfileController;
use App\Http\Controllers\Client\ClientDisputeController;
use App\Http\Controllers\Coach\CoachDashboardController;
use App\Http\Controllers\Coach\CoachDisputeController;
use App\Http\Controllers\Coach\CoachProfileController;
use App\Http\Controllers\Coach\CoachServiceController;
use App\Http\Controllers\Coach\CalendarController;
use App\Http\Controllers\Admin\WebsiteSettingsController;
use App\Http\Controllers\NewsletterController;
// use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CoachController;
use App\Http\Controllers\CoachFavoriteController;
use App\Http\Controllers\Admin\ClientController;
use App\Http\Controllers\Admin\AdminBookingController;
use App\Http\Controllers\Admin\AdminSoftLockController;
use App\Http\Controllers\Admin\SupportQuestionController;
// use App\Http\Controllers\ServiceFeedController;
use App\Http\Controllers\BookingController;
// use App\Http\Controllers\CoachSettingController;
use App\Http\Controllers\PayPalController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\ServiceAvailabilityController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\Me\TimezoneController;
use App\Http\Controllers\Client\HomeController as ClientHomeController;

use App\Http\Controllers\RoleSwitchController;
use App\Http\Controllers\Coach\CoachApplyController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Coach\ReservationReviewController as CoachReservationReviewController;



use App\Http\Controllers\ReserveController;
// use App\Http\Middleware\VerifyCsrfToken;

use App\Http\Controllers\SupportConversationController;

use App\Http\Controllers\Admin\SupportConversationAdminController;

use App\Http\Controllers\SuperAdmin\AdminController;


use App\Http\Controllers\SuperAdmin\Security\LockedStaffController;
use App\Http\Controllers\SuperAdmin\Security\AdminLogsController;
use App\Http\Controllers\SuperAdmin\Security\SecurityEventsController;





use App\Http\Controllers\SuperAdmin\SuperDashboardController;
// use App\Http\Controllers\SuperAdmin\SuperDisputeController;
use App\Http\Controllers\SuperAdmin\SuperBookingController;
use App\Http\Controllers\SuperAdmin\SuperTransactionController;
use App\Http\Controllers\SuperAdmin\SuperSupportConversationController;
use App\Http\Controllers\SuperAdmin\SuperCategoryController;
use App\Http\Controllers\SuperAdmin\SuperCoachController;
use App\Http\Controllers\SuperAdmin\SuperClientController;
use App\Http\Controllers\SuperAdmin\SuperWebsiteSettingsController;
use App\Http\Controllers\SuperAdmin\SuperServiceController;
use App\Http\Controllers\SuperAdmin\StaffController;
use App\Http\Controllers\SuperAdmin\StaffDocumentController;
use App\Http\Controllers\SuperAdmin\SuperadminDmController;

use App\Http\Controllers\SuperAdmin\EmailSubscriptionController;



Route::post('/me/timezone', [TimezoneController::class, 'update'])
    ->name('me.timezone.update')
    ->middleware('auth'); // applies to coach + client accounts

Route::post('/client/timezone', [TimezoneController::class, 'storeCookie'])
    ->name('client.timezone.store');


    Route::post('/admin/me/timezone', [TimezoneController::class, 'updateAdmin'])
  ->name('admin.me.timezone.update')
  ->middleware(['admin.access']); // or admin.not_locked etc



    // Route::post('/lock-timezone', function (Request $r) {
    //     $tz = (string) $r->input('timezone');
    //     $valid = in_array($tz, timezone_identifiers_list(), true);
    
    //     // Guests: store in session (useful for browsing before auth)
    //     if (! $r->user()) {
    //         if ($valid) {
    //             session(['guest_timezone' => $tz, 'guest_timezone_source' => 'browser']);
    //             return response()->json(['ok' => true, 'timezone' => $tz]);
    //         }
    //         return response()->json(['ok' => false, 'message' => 'Invalid timezone'], 422);
    //     }
    
    //     // Auth users: persist to DB
    //     $user = $r->user();
    //     if ($valid && $user->timezone !== $tz) {
    //         $user->forceFill([
    //             'timezone'        => $tz,
    //             'timezone_source' => 'browser',
    //         ])->save();
    //     }
    
    //     return response()->json(['ok' => true, 'timezone' => $user->timezone ?? $tz]);
    // })->name('lock.timezone');
    
/**
 * -------- Public ----------
 */

// Home (controller passes $services to the view)
Route::middleware(['staff.lock'])->group(function () {
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/coaches', fn () => view('coaches.index'))->name('coaches.index'); 
Route::get('/coaches/{coach}', [PublicCoachController::class, 'show'])
    ->name('coaches.show');

// Services catalogue + detail (pick ONE controller for index; we keep ServicesController)
Route::get('/services', [ServicesController::class, 'index'])->name('services.index');
Route::get('/services/{service}', [ServicesController::class, 'show'])->name('services.show');
// Optional extra feed page (kept; different route name)
// Route::get('/services/popular', [ServiceFeedController::class, 'popular'])->name('services.popular');

});

// Language
Route::post('/api/locale', function (Request $r) {
    $code = (string) $r->input('locale');
    if (! array_key_exists($code, config('locales'))) {
        return response()->json(['ok' => false], 422);
    }
    session(['locale' => $code]);
    app()->setLocale($code);
    return ['ok' => true];
})->name('api.locale');

// Auth pages
Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'authenticate'])->name('login.attempt');

Route::get('/register',  [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'create'])->name('register.store');

Route::get('/verify-email',         [VerificationController::class, 'show'])->name('auth.verify.show');
Route::post('/verify-email',        [VerificationController::class, 'verify'])->name('auth.verify.submit');
Route::post('/verify-email/resend', [VerificationController::class, 'resend'])->name('auth.verify.resend');
// Newsletter
Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->name('newsletter.subscribe');

// Logout
Route::post('/logout', function () {
    auth()->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    return redirect()->route('home');
})->name('logout');



Route::post('/role/switch', [RoleSwitchController::class, 'switch'])
    ->middleware('auth')
    ->name('role.switch');

/**
 * -------- Client ----------
 */



Route::middleware(['auth','staff.lock','acting:client'])

    ->prefix('client')
    ->name('client.')
    ->group(function () {

Route::get('disputes', [ClientDisputeController::class, 'index'])->name('disputes.index');
        Route::get('disputes/create/{reservation}', [ClientDisputeController::class, 'create'])->name('disputes.create');
        Route::post('disputes/{reservation}', [ClientDisputeController::class, 'store'])->name('disputes.store');
        Route::get('disputes/{dispute}', [ClientDisputeController::class, 'show'])->name('disputes.show');
        Route::post('disputes/{dispute}/message', [ClientDisputeController::class, 'message'])->name('disputes.message');
        Route::get('/bookings/{reservation}', [\App\Http\Controllers\Client\BookingShowController::class, 'show'])
        ->name('bookings.show');

      Route::get('messages/{conversation}', [MessageController::class, 'clientShow'])->name('messages.show');



      Route::post('/reservations/{reservation}/review', [ReservationReviewController::class, 'store'])
        ->name('reviews.store');

    Route::post('messages/{conversation}', [MessageController::class, 'store'])
    ->name('messages.store');

   
       

    // Dashboard (client home)
    Route::get('home', [DashboardController::class, 'home'])
        ->name('home');

    // Messages
   Route::get('messages', [MessageController::class, 'index'])
    ->name('messages.index');


    // Disputes
   

    // Cancellations
    Route::get('cancellations', [DashboardController::class, 'cancellations'])
        ->name('cancellations');

    // Profile
    Route::get('profile/edit', [ClientProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::put('profile', [ClientProfileController::class, 'update'])
        ->name('profile.update');
    Route::put('profile/password', [ClientProfileController::class, 'updatePassword'])
        ->name('profile.password');

    // BOOKINGS
    Route::get('bookings', [BookingController::class, 'clientIndex'])
        ->name('bookings.index');

    Route::get('bookings/{reservation}', [BookingController::class, 'clientShow'])
        ->whereNumber('reservation')
        ->name('bookings.show');

        Route::post('bookings/{reservation}/complete', [BookingController::class, 'clientComplete'])
    ->whereNumber('reservation')
    ->name('bookings.complete');

   
 Route::post('/slots/{slot}/client-checkin',
    [ReservationSlotSessionController::class, 'clientCheckin']
)->name('slots.client.checkin');

Route::post('/slots/{slot}/refund',
    [ReservationSlotSessionController::class, 'clientRequestRefund']
)->name('slots.request_refund');

Route::post('/slots/{slot}/extend-wait',
    [ReservationSlotSessionController::class, 'extendWait']
)->name('slots.extend_wait');


  Route::post('/reservations/{reservation}/cancel', [App\Http\Controllers\ReservationCancelController::class, 'clientCancel'])
    ->whereNumber('reservation')
    ->name('reservations.cancel');
    Route::get('/reservations/{reservation}/cancel-quote', [App\Http\Controllers\ReservationCancelController::class, 'clientCancelQuote'])
  ->whereNumber('reservation')
  ->name('reservations.cancel_quote');
  // ✅ Client chooses refund destination (wallet_credit / original_payment)
Route::post('/reservations/{reservation}/refund/choose', [RefundChoiceController::class, 'choose'])
    ->whereNumber('reservation')
    ->name('reservations.refund.choose');

// ✅ Optional: get refund info for UI (status + amount)
Route::get('/reservations/{reservation}/refund', [RefundChoiceController::class, 'show'])
    ->whereNumber('reservation')
    ->name('reservations.refund.show');




    
});






Route::get('/forgot-password', [PasswordResetController::class, 'request'])
    ->middleware('guest')
    ->name('password.request');

Route::post('/forgot-password', [PasswordResetController::class, 'email'])
    ->middleware('guest')
    ->name('password.email');

Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])
    ->middleware('guest')
    ->name('password.reset');

Route::post('/reset-password', [PasswordResetController::class, 'update'])
    ->middleware('guest')
    ->name('password.update');


Route::middleware(['auth'])->group(function () {

    
  
    Route::post('/services/{service}/favorite', [ServiceFavoriteController::class, 'toggle'])
        ->name('services.favorite.toggle');
        Route::get('/favorites', [FavoritesController::class, 'index'])
        ->name('favorites.index');

        Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profile', [ProfileController::class, 'deactivate'])->name('profile.deactivate');
        
    Route::get('/slots/{slot}/call', [SlotCallController::class, 'join'])
        ->name('slots.call');

        Route::post('/coaches/{coach}/favorite', 
        [CoachFavoriteController::class, 'toggle'])
        ->name('coaches.favorite.toggle');

    // Start from a service page
    Route::get('/services/{service}/messages/start', [MessageController::class, 'startFromService'])
        ->name('messages.start.fromService');

    // Support chat (page / widget)
    Route::get('/support', [SupportConversationController::class, 'index'])
        ->name('support.conversation.index');

    Route::post('/support/message', [SupportConversationController::class, 'storeMessage'])
        ->name('support.message.store');

       

    // 🔥 NEW: AJAX polling for latest messages
    Route::get('/support/messages/latest', [SupportConversationController::class, 'latest'])
        ->name('support.messages.latest');

        Route::post('/support/{conversation}/rate', [SupportConversationController::class, 'rate'])
    ->name('support.conversation.rate');
    Route::middleware('auth')->get(
    '/api/slots/{slot}/status',
    [ReservationSlotSessionController::class, 'status']
)->name('api.slots.status');

});




Route::middleware('auth')->group(function () {
    Route::get('/reserve', [ReserveController::class, 'show'])->name('reserve.show');
    Route::post('/reserve', [ReserveController::class, 'store'])->name('reserve.store'); // creates booking draft/order
});

// Only creating the PayPal payment requires an authenticated user
Route::middleware(['auth'])->group(function () {
    Route::post('paypal/create', [PayPalController::class, 'create'])
        ->name('paypal.create');

    Route::get('paypal/success', [PayPalController::class, 'success'])
        ->name('paypal.success');

    Route::get('paypal/cancel', [PayPalController::class, 'cancel'])
        ->name('paypal.cancel');
});

// public webhook endpoint
Route::post('webhooks/paypal', [PayPalWebhookController::class, 'handle'])
    ->name('paypal.webhook');



// (Optional) lightweight JSON to recompute totals if needed (AJAX on reserve page)
Route::middleware('auth')->post('/reserve/reprice', [ReserveController::class, 'reprice'])
    ->name('reserve.reprice');

/**
 * -------- Coach ----------
 */

/*
|--------------------------------------------------------------------------
| Coach Application / Onboarding
|--------------------------------------------------------------------------
*/

Route::middleware(['auth','staff.lock'])
    ->prefix('coach/application')
    ->name('coach.application.')
    ->group(function () {

        Route::get('/', [CoachApplicationController::class, 'show'])
            ->name('show');

        Route::post('/', [CoachApplicationController::class, 'store'])
            ->name('store');

        Route::get('/review', [CoachApplicationController::class, 'review'])
            ->name('review');
    });
Route::middleware(['auth', 'staff.lock', 'acting:coach', 'coach.lock', 'coach.payout.ready'])
    ->prefix('coach')
    ->name('coach.')
    ->group(function () {
       Route::get('payouts', [CoachPayoutController::class, 'settings'])
            ->name('payouts.settings');

        Route::post('payouts/start', [CoachPayoutController::class, 'start'])
            ->name('payouts.start');

        Route::post('payouts/refresh', [CoachPayoutController::class, 'refresh'])
            ->name('payouts.refresh');

        Route::get('payouts/stripe/return', [CoachPayoutController::class, 'providerReturn'])
            ->name('payouts.stripe.return');

        Route::post('payouts/methods/default', [CoachPayoutController::class, 'setDefaultMethod'])
            ->name('payouts.methods.default');
    // Route::get('settings', [CoachSettingsController::class, 'edit'])->name('settings.edit');
    // Route::post('settings/timezone', [CoachSettingsController::class, 'setTimezone'])->name('settings.tz.set');
    Route::get('home', [CoachDashboardController::class, 'index'])->name('home');


    Route::post('/reservations/{reservation}/review', [CoachReservationReviewController::class, 'store'])
    ->whereNumber('reservation')
    ->name('reviews.store');
    
    Route::get('bookings', [BookingController::class, 'coachIndex'])
        ->name('bookings'); // used by route('coach.bookings') in your sidebar

        Route::post('bookings/{reservation}/complete', [BookingController::class, 'coachComplete'])
    ->whereNumber('reservation')
    ->name('bookings.complete');


   

    Route::get('bookings/{reservation}', [BookingController::class, 'coachShow'])
        ->whereNumber('reservation')
        ->name('bookings.show');

    // If these point to a shared DashboardController, keep as-is:
    
    // Coach Disputes

           Route::get('/disputes', [CoachDisputeController::class,'index'])->name('disputes.index');
Route::get('/disputes/create/{reservation}', [CoachDisputeController::class,'create'])->name('disputes.create');
Route::post('/disputes/{reservation}', [CoachDisputeController::class,'store'])->name('disputes.store');
Route::get('/disputes/{dispute}', [CoachDisputeController::class,'show'])->name('disputes.show');
Route::post('/disputes/{dispute}/message', [CoachDisputeController::class,'message'])->name('disputes.message');

Route::get('qualifications', [DashboardController::class, 'index'])->name('qualifications');


    Route::get('messages', [MessageController::class, 'coachIndex'])->name('messages.index');
    Route::get('messages/{conversation}', [MessageController::class, 'coachShow'])->name('messages.show');

    Route::post('messages/{conversation}', [MessageController::class, 'store'])
    ->name('messages.store');


    // -------- Calendar (coach) ----------
   Route::get('calendar', [CalendarController::class,'index'])->name('calendar.index');
    Route::get('calendar/events',        [CalendarController::class,'events'])->name('calendar.events');

    Route::get('calendar/schedule',      [CalendarController::class,'getSchedule'])->name('calendar.schedule.get');
    Route::post('calendar/schedule',     [CalendarController::class,'saveSchedule'])->name('calendar.schedule.save');

    Route::post('calendar/unavail',      [CalendarController::class,'storeUnavailability'])->name('calendar.unavailable.store');
    Route::delete('calendar/unavail',    [CalendarController::class,'clearUnavailability'])->name('calendar.unavailable.clear');

    Route::post('/calendar/availability-override', [CalendarController::class, 'storeAvailabilityOverride'])
    ->name('calendar.avail_override.store');


   Route::post('/slots/{slot}/coach-checkin', [ReservationSlotSessionController::class, 'coachCheckin'])
    ->name('slots.coach.checkin');

   Route::post('/reservations/{reservation}/cancel', [App\Http\Controllers\Coach\ReservationCancelController::class, 'coachCancel'])
    ->whereNumber('reservation')
    ->name('reservations.cancel');
    Route::get('/reservations/{reservation}/cancel-quote', [App\Http\Controllers\Coach\ReservationCancelController::class, 'cancelQuote'])
  ->whereNumber('reservation')
  ->name('reservations.cancel_quote');

    Route::post('/slots/{slot}/extend-wait', [ReservationSlotSessionController::class, 'coachExtendWait'])
  ->name('slots.extend_wait');



    // Services CRUD
    Route::get('services',                 [CoachServiceController::class, 'index'])->name('services.index');
    Route::get('services/create',          [CoachServiceController::class, 'create'])->name('services.create');
    Route::post('services',                [CoachServiceController::class, 'store'])->name('services.store');
    Route::get('services/{service}/edit',  [CoachServiceController::class, 'edit'])->name('services.edit');
    Route::put('services/{service}',       [CoachServiceController::class, 'update'])->name('services.update');
    Route::delete('services/{service}',    [CoachServiceController::class, 'destroy'])->name('services.destroy');
    Route::post('services/{service}/toggle',[CoachServiceController::class, 'toggle'])->name('services.toggle');

    // Wallet
   // Withdraw (Coach)
 Route::get('withdraw', [CoachWithdrawController::class, 'index'])->name('withdraw.index');
    Route::post('withdraw', [CoachWithdrawController::class, 'store'])->name('withdraw.store');


    Route::get('withdraw/{payout}/receipt', [CoachWithdrawController::class, 'receipt'])
    ->name('withdraw.receipt');

Route::get('withdraw/{payout}/receipt/download', [CoachWithdrawController::class, 'downloadReceipt'])
    ->name('withdraw.receipt.download');

    // Payout methods
    Route::post('payout-methods', [CoachWithdrawController::class, 'storeMethod'])->name('payout_methods.store');
    Route::post('payout-methods/{method}/default', [CoachWithdrawController::class, 'makeDefault'])->name('payout_methods.default');
    Route::delete('payout-methods/{method}', [CoachWithdrawController::class, 'destroyMethod'])->name('payout_methods.destroy');


  
    // Profile
    Route::get('profile/edit',     [CoachProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile',          [CoachProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [CoachProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('profile/deactivate', [CoachProfileController::class, 'deactivate'])->name('profile.deactivate');


    // test 
    Route::post('withdraw/{withdrawal}/test-release', [CoachWithdrawController::class, 'testRelease'])
    ->name('withdraw.test_release');



    });

    Route::post('/lock-timezone', [CalendarController::class,'lockTimezone'])->name('lock.timezone');

/**
 * -------- Dashboard redirect ----------
 */
Route::get('/dashboard', function () {
    $u = auth()->user();
    if (! $u) return redirect()->route('login');

    $role = strtolower((string) ($u->role ?? ''));

    if (in_array($role, ['admin','manager','super_admin'], true) || session('panel') === 'admin') {
        return redirect()->route('admin.dashboard');
    }

    $active = strtolower((string) session('active_role', 'client'));

    return $active === 'coach'
        ? redirect()->route('coach.home')
        : redirect()->route('client.home');
})->middleware('auth')->name('dashboard');



/**
 * -------- Public ----------
 * If acting as coach, redirect to coach dashboard (Fiverr-like).
 */
// Route::middleware(['coach.lock'])->group(function () {
//     Route::get('/', [HomeController::class, 'index'])->name('home');

//     Route::get('/coaches', fn () => view('coaches.index'))->name('coaches.index');
//     Route::get('/coaches/{coach}', [PublicCoachController::class, 'show'])
//         ->name('coaches.show');

//     Route::get('/services', [ServicesController::class, 'index'])->name('services.index');
//     Route::get('/services/{service}', [ServicesController::class, 'show'])->name('services.show');
// });


//Booking
// Route::middleware(['auth'])->prefix('bookings')->name('bookings.')->group(function () {
//     Route::get('/', [BookingController::class, 'index'])->name('index');
//     Route::get('/create', [BookingController::class, 'create'])->name('create');
//     Route::post('/', [BookingController::class, 'store'])->name('store');
// });



Route::get('/services/{service}/availability', [ServiceAvailabilityController::class, 'show'])
    ->name('services.availability');


    // routes/web.php
    Route::get('/services/{service}/availability/day', [ServiceAvailabilityController::class, 'day'])
    ->name('services.availability.day');

    

    // routes/web.php
// Route::post('/coach/timezone', [CoachSettingsController::class,'setTimezone'])
// ->middleware('auth')->name('coach.tz.set');




Route::prefix('payments')->name('payments.')->group(function () {
    Route::post('/intent',  [PaymentController::class,'createIntent'])->name('intent');
    Route::get('/success',  [PaymentController::class,'success'])->name('success');
   
});

// Route::post('stripe/webhook', [PaymentController::class, 'webhook'])
//     ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);


    



//Admin

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('admin.guest')->group(function () {
        Route::get('login', [LoginController::class, 'showAdmin'])->name('login');
        Route::post('login', [LoginController::class, 'loginAdmin'])
            ->middleware('throttle:login')
            ->name('login.submit');


            Route::get('forgot-password', [StaffPasswordResetController::class, 'request'])
            ->name('password.request');

        Route::post('forgot-password', [StaffPasswordResetController::class, 'email'])
            ->middleware('throttle:5,10')
            ->name('password.email');

        Route::get('reset-password/{token}', [StaffPasswordResetController::class, 'reset'])
            ->name('password.reset');

        Route::post('reset-password', [StaffPasswordResetController::class, 'update'])
            ->middleware('throttle:5,10')
            ->name('password.update');
    });

   

   Route::post('logout', [LoginController::class, 'logoutAdmin'])
        ->middleware('auth')
        ->name('logout');

    // ✅ ADD THIS (outside protected group)
    Route::get('locked', [AdminLockedController::class, 'notice'])
        ->name('locked.notice');

        Route::get('locked-soft', [AdminSoftLockController::class, 'notice'])->name('locked.soft');
Route::post('locked-soft/unlock', [AdminSoftLockController::class, 'unlock'])->name('locked.soft.unlock');
Route::post('soft-lock', [AdminSoftLockController::class, 'trigger'])->name('softlock.trigger');


Route::middleware(['auth', 'admin.access', 'admin.not_locked', 'admin.softlock'])
    ->withoutMiddleware([VerifyCsrfToken::class]) // ✅ THIS IS THE KEY FIX
    ->group(function () {

        Route::get('webauthn/register', [AdminWebAuthnController::class, 'showRegister'])
            ->name('webauthn.register');

        Route::get('webauthn/verify', [AdminWebAuthnController::class, 'showVerify'])
            ->name('webauthn.verify');

        Route::post('webauthn/register/options', [AdminWebAuthnController::class, 'registerOptions'])
            ->name('webauthn.register.options');

        Route::post('webauthn/register/store', [AdminWebAuthnController::class, 'registerStore'])
            ->name('webauthn.register.store');

        Route::post('webauthn/verify/options', [AdminWebAuthnController::class, 'verifyOptions'])
            ->name('webauthn.verify.options');

        Route::post('webauthn/verify/store', [AdminWebAuthnController::class, 'verifyStore'])
            ->name('webauthn.verify.store');
    });
    // ✅ Protected admin app
    Route::middleware(['auth','admin.access','admin.not_locked','admin.softlock','admin.passkey'])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    


//         Route::middleware('acting:manager')->prefix('support/reviews')->name('support.reviews.')->group(function () {
    
// });


Route::get('disputes', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'index'])
      ->name('disputes.index');

      

      Route::get('/profile', [\App\Http\Controllers\Admin\AdminProfileController::class, 'edit'])
  ->name('profile.edit');

Route::put('/profile', [\App\Http\Controllers\Admin\AdminProfileController::class, 'update'])
  ->name('profile.update');

    Route::get('disputes/{dispute}', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'show'])
      ->name('disputes.show');
      

     Route::post('disputes/{dispute}/close', [AdminDisputeController::class, 'closeConversation'])
    ->name('disputes.close');

    
    Route::get('/bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
    Route::get('/bookings/{reservation}', [AdminBookingController::class, 'show'])->name('bookings.show');

   

    Route::post('disputes/{dispute}/status', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'updateStatus'])
      ->name('disputes.status');

    Route::post('disputes/{dispute}/message', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'message'])
      ->name('disputes.message');

    Route::post('disputes/{dispute}/finalize', [\App\Http\Controllers\Admin\AdminDisputeController::class, 'finalize'])
      ->name('disputes.finalize');
    // Admin Dispute ownership + decision actions



    Route::get('transactions', [AdminTransactionController::class, 'index'])->name('transactions.index');
Route::get('transactions/{reservation}', [AdminTransactionController::class, 'show'])
    ->whereNumber('reservation')
    ->name('transactions.show');

// optional: recompute (safe button)
Route::post('transactions/{reservation}/recompute', [AdminTransactionController::class, 'recompute'])
    ->whereNumber('reservation')
    ->name('transactions.recompute');




        Route::get('/metrics/active-visitors', [AdminDashboardController::class, 'activeVisitors'])
        ->name('metrics.active-visitors');

        Route::get('/support/conversations', [SupportConversationAdminController::class, 'index'])
        ->name('support.conversations.index');
                // Search within a single conversation (AJAX)
        Route::get('/support/conversations/{conversation}/search', [SupportConversationAdminController::class, 'search'])
            ->name('support.conversations.search');
            Route::get('/support/reviews', [SupportReviewAnalyticsController::class, 'index'])
        ->name('support.reviews.index');


            Route::post('support/conversations/{conversation}/admin-resolve',  [SupportConversationAdminController::class, 'adminResolve'])
    ->name('support.conversations.adminResolve');

Route::post('support/conversations/{conversation}/manager-resolve', [SupportConversationAdminController::class, 'managerResolve'])
    ->name('support.conversations.managerResolve');


    // Show a single conversation (chat screen)
    Route::get('/support/conversations/{conversation}', [SupportConversationAdminController::class, 'show'])
        ->name('support.conversations.show');

    // Admin sends a message
    Route::post('/support/conversations/{conversation}/message', [SupportConversationAdminController::class, 'storeMessage'])
        ->name('support.conversations.message.store');

    // Poll for new messages (AJAX)
    Route::get('/support/messages/latest', [SupportConversationAdminController::class, 'latest'])
        ->name('support.messages.latest');


        Route::post('/support/agent/status', [\App\Http\Controllers\Admin\SupportAgentStatusController::class, 'update'])
  ->name('support.agent.status.update');

    // Admin changes status (open / closed)
    Route::post('/support/conversations/{conversation}/status', [SupportConversationAdminController::class, 'updateStatus'])
        ->name('support.conversations.status');
        Route::post('/support/heartbeat', [\App\Http\Controllers\Admin\SupportAgentStatusController::class, 'heartbeat'])
    ->name('support.heartbeat');
Route::post('/support/go-offline', [\App\Http\Controllers\Admin\SupportAgentStatusController::class, 'goOffline'])
  ->name('support.go_offline');

        Route::post('/support/conversations/{conversation}/read', [SupportConversationAdminController::class, 'markRead'])
  ->name('support.conversations.read');
    // Admin takes ownership of conversation
    Route::post('/support/conversations/{conversation}/assign-me', [SupportConversationAdminController::class, 'assignMe'])
        ->name('support.conversations.assignMe');



        // Agent asks manager
Route::post('/support/conversations/{conversation}/request-manager',
  [SupportConversationAdminController::class, 'requestManager']
)->name('support.conversations.requestManager');

// Manager joins
Route::post('/support/conversations/{conversation}/manager-join',
  [SupportConversationAdminController::class, 'managerJoin']
)->name('support.conversations.managerJoin');

// Manager ends
Route::post('/support/conversations/{conversation}/manager-end',
  [SupportConversationAdminController::class, 'managerEnd']
)->name('support.conversations.managerEnd');
Route::post('/support/conversations/{conversation}/admin-end',
  [SupportConversationAdminController::class, 'adminEnd']
)->name('support.conversations.adminEnd');



Route::get('/support/questions', [\App\Http\Controllers\Admin\SupportQuestionController::class, 'index'])
    ->name('support.questions.index');

  Route::get('/support/questions/create', [\App\Http\Controllers\Admin\SupportQuestionController::class, 'create'])
    ->name('support.questions.create');

  Route::post('/support/questions', [\App\Http\Controllers\Admin\SupportQuestionController::class, 'store'])
    ->name('support.questions.store');

  Route::get('/support/questions/{question}', [\App\Http\Controllers\Admin\SupportQuestionController::class, 'show'])
    ->name('support.questions.show');

  Route::post('/support/questions/{question}/message', [\App\Http\Controllers\Admin\SupportQuestionController::class, 'storeMessage'])
    ->name('support.questions.message');

  Route::post('/support/questions/{question}/ack', [\App\Http\Controllers\Admin\SupportQuestionController::class, 'acknowledge'])
    ->name('support.questions.ack');

    Route::post('/support/questions/{question}/take', [SupportQuestionController::class,'take'])
  ->name('support.questions.take');

Route::post('/support/questions/{question}/acknowledge', [SupportQuestionController::class,'acknowledge'])
  ->name('support.questions.acknowledge');



   // =============================
// STAFF CHAT (Admin + Manager)
// =============================
Route::prefix('staff-chat')->name('staff_chat.')->group(function () {

    // main screen (sidebar + open room)
    Route::get('/', [\App\Http\Controllers\Admin\StaffChatController::class, 'index'])
        ->name('index');

    // open any room I am member of
    Route::get('/room/{room}', [\App\Http\Controllers\Admin\StaffChatController::class, 'show'])
        ->whereNumber('room')
        ->name('show');

    // start/open dm room with a user (returns room_id or redirects)
    Route::post('/dm/start', [\App\Http\Controllers\Admin\StaffChatController::class, 'startDm'])
        ->name('dm.start');

    // send message + multi attachments
    Route::post('/room/{room}/send', [\App\Http\Controllers\Admin\StaffChatController::class, 'send'])
        ->whereNumber('room')
        ->name('send');

    // mark room as read (on open)
    Route::post('/room/{room}/read', [\App\Http\Controllers\Admin\StaffChatController::class, 'markRead'])
        ->whereNumber('room')
        ->name('read');

    // unread counts for sidebar badges (poll)
    Route::get('/unreads', [\App\Http\Controllers\Admin\StaffChatController::class, 'unreads'])
        ->name('unreads');
});


// Support Absence Workflow
// Support Absence Workflow
// Route::prefix('support/absence')->name('support.absence.')->group(function () {

//   // ADMIN (agent/admin)
//   Route::get('/', [\App\Http\Controllers\Admin\SupportAbsenceController::class, 'my'])
//     ->name('my');

//   Route::post('/request', [\App\Http\Controllers\Admin\SupportAbsenceController::class, 'requestAbsence'])
//     ->name('request');

//   Route::post('/cancel/{request}', [\App\Http\Controllers\Admin\SupportAbsenceController::class, 'cancel'])
//     ->whereNumber('request')
//     ->name('cancel');

//   Route::get('/my-log', [\App\Http\Controllers\Admin\SupportAbsenceController::class, 'myLog'])
//     ->name('my_log');

//   Route::get('/my-log/{audit}/download', [\App\Http\Controllers\Admin\SupportAbsenceController::class, 'downloadMyFile'])
//     ->whereNumber('audit')
//     ->name('my_log.download');

//   // MANAGER
//   Route::get('/review', [\App\Http\Controllers\Admin\SupportAbsenceController::class, 'review'])
//     ->name('review');

//   Route::post('/decide/{request}', [\App\Http\Controllers\Admin\SupportAbsenceController::class, 'decide'])
//     ->whereNumber('request')
//     ->name('decide');


//     Route::get('/my-request-file/{file}', [\App\Http\Controllers\Admin\SupportAbsenceController::class, 'downloadMyRequestFile'])
//   ->whereNumber('file')
//   ->name('my_request_file.download');

// // Manager downloads any pending/approved request file
// Route::get('/review-request-file/{file}', [\App\Http\Controllers\Admin\SupportAbsenceController::class, 'downloadReviewRequestFile'])
//   ->whereNumber('file')
//   ->name('review_request_file.download');

// });




// =============================
// SUPPORT LEAVE (Absence + Holiday)
// Admin applies to Manager
// Manager applies to Superadmin
// =============================
Route::prefix('support/leave')
    ->name('support_leave.')
    ->group(function () {

        // My requests (Admin + Manager)
        Route::get('/', [SupportLeaveController::class, 'my'])
            ->name('my');

        Route::post('/request', [SupportLeaveController::class, 'requestLeave'])
            ->name('request');

        Route::post('/cancel/{request}', [SupportLeaveController::class, 'cancel'])
            ->whereNumber('request')
            ->name('cancel');

        Route::get('/my-log', [SupportLeaveController::class, 'myLog'])
            ->name('my_log');

        Route::get('/my-log/{audit}/download', [SupportLeaveController::class, 'downloadMyFile'])
            ->whereNumber('audit')
            ->name('my_log.download');

        Route::get('/my-request-file/{file}', [SupportLeaveController::class, 'downloadMyRequestFile'])
            ->whereNumber('file')
            ->name('my_request_file.download');

        // Manager review (admin requests)
        Route::middleware('acting:manager')->group(function () {


            Route::get('/review', [SupportLeaveController::class, 'review'])
                ->name('review');

            Route::post('/decide/{request}', [SupportLeaveController::class, 'decide'])
                ->whereNumber('request')
                ->name('decide');

            Route::get('/review-request-file/{file}', [SupportLeaveController::class, 'downloadReviewRequestFile'])
                ->whereNumber('file')
                ->name('review_request_file.download');

               Route::get('/{request}', [SupportLeaveController::class, 'show'])
    ->whereNumber('request')
    ->name('show');

        });
    });


         


    Route::get('manager/dm', [ManagerDmController::class, 'index'])->name('dm.manager.index');
Route::get('manager/dm/thread/{thread}', [ManagerDmController::class, 'show'])->name('dm.manager.show');
Route::post('manager/dm/thread/{thread}/send', [ManagerDmController::class, 'send'])->name('dm.manager.send');
Route::get('manager/dm/thread/{thread}/latest', [ManagerDmController::class, 'latest'])->name('dm.manager.latest');




// Agent/Admin -> DM Manager (personal chat)
Route::get('agent/dm', [AgentDmController::class, 'index'])->name('dm.agent.index');
Route::get('agent/dm/thread/{thread}', [AgentDmController::class, 'show'])->name('dm.agent.show');
Route::post('agent/dm/thread/{thread}/send', [AgentDmController::class, 'send'])->name('dm.agent.send');
Route::get('agent/dm/thread/{thread}/latest', [AgentDmController::class, 'latest'])->name('dm.agent.latest');

//         Route::get('/categories',                 [CategoryController::class, 'index'])->name('categories.index');
// Route::post('/categories',                [CategoryController::class, 'store'])->name('categories.store');
// Route::put('/categories/{category}',      [CategoryController::class, 'update'])->name('categories.update');
// Route::post('/categories/{category}/on',  [CategoryController::class, 'activate'])->name('categories.activate');
// Route::post('/categories/{category}/off', [CategoryController::class, 'deactivate'])->name('categories.deactivate');
// Route::delete('/categories/{category}',   [CategoryController::class, 'destroy'])->name('categories.destroy');
  
 Route::get('coaches', [CoachController::class,'index'])->name('coaches.index');
    Route::post('coaches/{user}/approve', [CoachController::class,'approve'])->name('coaches.approve');
    Route::post('coaches/{user}/reject',  [CoachController::class,'reject'])->name('coaches.reject');
    Route::delete('coaches/{user}',       [CoachController::class,'destroy'])->name('coaches.destroy');
    Route::post('coaches/{id}/restore', [CoachController::class, 'restore'])->name('coaches.restore');

    Route::get('coaches/{user}', [CoachController::class, 'show'])->name('coaches.show');

    Route::get('/coaches/{coach}/stats', [AdminCoachAnalyticsController::class, 'show'])
    ->name('coaches.stats');

    Route::get('/refunds/analytics', [ManagerRefundAnalyticsController::class, 'index'])
        ->name('refunds.analytics');

    // Deactivation requests page (point this to your controller/view)
    Route::get('coaches/deactivations', fn() => view('admin.coaches.deactivations'))
        ->name('coaches.deactivations');




        // Admin Clients
Route::get('clients', [ClientController::class, 'index'])
    ->name('clients.index');

    Route::get('/clients/{client}/stats', [AdminClientAnalyticsController::class, 'show'])
    ->name('clients.stats');
     Route::get('withdrawals', [AdminWithdrawalController::class, 'index'])
    ->name('withdrawals.index');

// IMPORTANT: use {id} so show can use withTrashed()
Route::get('clients/{id}', [ClientController::class, 'show'])
    ->whereNumber('id')
    ->name('clients.show');

// Soft delete
Route::delete('clients/{user}', [ClientController::class, 'destroy'])
    ->name('clients.destroy');

// ✅ Restore
Route::post('clients/{id}/restore', [ClientController::class, 'restore'])
    ->whereNumber('id')
    ->name('clients.restore');





    Route::get('/support/status/analytics', [SupportAgentStatusAnalyticsController::class, 'index'])
        ->name('support.status.analytics');

    Route::get('/support/status/analytics/data', [SupportAgentStatusAnalyticsController::class, 'data'])
        ->name('support.status.analytics.data');

//     Route::get('settings', function () {
//         return redirect()->route('admin.settings.trainer.edit');
//     })->name('settings.index');

//     // Trainer settings
//     Route::get('settings/trainer',  [WebsiteSettingsController::class,'trainerEdit'])->name('settings.trainer.edit');
//     Route::post('settings/trainer', [WebsiteSettingsController::class,'trainerUpdate'])->name('settings.trainer.update');

//     // Customer settings
//     Route::get('settings/customer',  [WebsiteSettingsController::class,'customerEdit'])->name('settings.customer.edit');
//     Route::post('settings/customer', [WebsiteSettingsController::class,'customerUpdate'])->name('settings.customer.update');


//     // Appearance / site customization
//     Route::get('settings/appearance',  [WebsiteSettingsController::class,'appearanceEdit'])->name('settings.appearance.edit');
//     Route::post('settings/appearance', [WebsiteSettingsController::class,'appearanceUpdate'])->name('settings.appearance.update');


//     Route::post('/settings/trainer/default-cover/delete', [\App\Http\Controllers\Admin\WebsiteSettingsController::class, 'trainerDefaultCoverDelete'])
//     ->name('settings.trainer.default_cover.delete');

// Route::post('/settings/appearance/search-bg/delete', [\App\Http\Controllers\Admin\WebsiteSettingsController::class, 'appearanceSearchBgDelete'])
//     ->name('settings.appearance.search_bg.delete');

// Route::post('/settings/appearance/middle-banner/delete', [\App\Http\Controllers\Admin\WebsiteSettingsController::class, 'appearanceMiddleBannerDelete'])
//     ->name('settings.appearance.middle_banner.delete');






   // Admin Services
Route::get('services', [AdminServiceController::class, 'index'])
    ->name('services.index');

// "Service Request" menu (pending by default)
Route::get('services/requests', [AdminServiceController::class, 'requests'])
    ->name('services.requests');

    

// Detail (IMPORTANT: use {id} if your controller show($id) uses withTrashed)
Route::get('services/{id}', [AdminServiceController::class, 'show'])
    ->whereNumber('id')
    ->name('services.show');

// Actions
Route::post('services/{service}/approve', [AdminServiceController::class, 'approve'])
    ->name('services.approve');

Route::post('services/{service}/reject', [AdminServiceController::class, 'reject'])
    ->name('services.reject');

Route::post('services/{service}/toggle-active', [AdminServiceController::class, 'toggleActive'])
    ->name('services.toggleActive');

// ✅ Soft delete + restore
Route::delete('services/{service}', [AdminServiceController::class, 'destroy'])
    ->name('services.destroy');

Route::post('services/{id}/restore', [AdminServiceController::class, 'restore'])
    ->whereNumber('id')
    ->name('services.restore');

    // Admin lock (disable/enable)
Route::post('services/{service}/disable', [AdminServiceController::class, 'disable'])
    ->name('services.disable');

Route::post('services/{service}/enable', [AdminServiceController::class, 'enable'])
    ->name('services.enable');

    // Manager Analytics (view any admin performance)
Route::get('/support/status/analytics/manager', [\App\Http\Controllers\Admin\SupportAgentStatusAnalyticsManagerController::class, 'index'])
    ->name('support.status.analytics.manager');

Route::get('/support/status/analytics/manager/data', [\App\Http\Controllers\Admin\SupportAgentStatusAnalyticsManagerController::class, 'data'])
    ->name('support.status.analytics.manager.data');

    Route::get('/staff-analytics', [\App\Http\Controllers\Admin\ManagerStaffAnalyticsController::class, 'index'])
        ->name('staff_analytics.index');


});
   



});

   

    

// Country/City helper endpoints
Route::get('/cc/countries', [CountryCityController::class, 'countries'])->name('cc.countries');
Route::get('/cc/cities',    [CountryCityController::class, 'cities'])->name('cc.cities');
Route::get('/cc/codes',     [CountryCityController::class, 'codes'])->name('cc.codes');
Route::get('/cc/locations/search', [CountryCityController::class, 'locationSearch'])
  ->name('cc.locations.search');

  // routes/web.php
Route::get('/cc/categories/search', [\App\Http\Controllers\CountryCityController::class, 'categoriesSearch'])
  ->name('cc.categories.search');








Route::get('auth/google', [LoginController::class, 'redirectToGoogle'])
    ->name('login.google');

// Callback from Google
Route::get('auth/google/callback', [LoginController::class, 'handleGoogleCallback'])
    ->name('login.google.callback');


    Route::middleware('auth')->group(function () {
    Route::get('/onboarding', [OnboardingController::class, 'show'])
        ->name('onboarding.show');

    Route::post('/onboarding', [OnboardingController::class, 'store'])
        ->name('onboarding.store');

       

});





Route::prefix('superadmin')
    ->middleware(['auth', 'superadmin'])
    ->name('superadmin.')
    ->group(function () {

        Route::get('/dashboard', [App\Http\Controllers\SuperAdmin\DashboardController::class, 'index'])
            ->name('dashboard');
           


        // Manage admins
        Route::get('/admins', [App\Http\Controllers\SuperAdmin\AdminController::class,'index'])
            ->name('admins.index');

        Route::post('/admins/{admin}/lock', [App\Http\Controllers\SuperAdmin\AdminController::class,'lock'])
            ->name('admins.lock');

        Route::post('/admins/{admin}/unlock', [App\Http\Controllers\SuperAdmin\AdminController::class,'unlock'])
            ->name('admins.unlock');
    });



    // SUPER ADMIN AUTH (Login / Logout)
Route::prefix('superadmin')->name('superadmin.')->group(function () {

    Route::get('/login', [App\Http\Controllers\SuperAdmin\AuthController::class, 'showLoginForm'])
        ->name('login')
        ->middleware('guest');

    Route::post('/login', [App\Http\Controllers\SuperAdmin\AuthController::class, 'login'])
        ->name('login.submit')
        ->middleware('guest');

    Route::post('/logout', [App\Http\Controllers\SuperAdmin\AuthController::class, 'logout'])
        ->name('logout')
        ->middleware('auth');




});









Route::middleware(['redirect.if.coach'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('home');
    Route::get('/services', [ServicesController::class, 'index'])->name('services.index');
    Route::get('/services/{service}', [ServicesController::class, 'show'])->name('services.show');

    Route::get('/coaches', fn () => view('coaches.index'))->name('coaches.index');
    Route::get('/coaches/{coach}', [PublicCoachController::class, 'show'])->name('coaches.show');
});









Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);








    Route::view('/help-center', 'support.help_center')->name('help.center');







    Route::prefix('superadmin')
    ->name('superadmin.')
    ->middleware(['auth', 'superadmin'])   // ✅ your SuperAdminMiddleware
    ->group(function () {

        Route::get('/', [SuperDashboardController::class, 'index'])->name('dashboard');

        // DISPUTES
        Route::get('disputes', [SuperDisputeController::class, 'index'])->name('disputes.index');
        Route::get('disputes/{dispute}', [SuperDisputeController::class, 'show'])->name('disputes.show');
        Route::post('disputes/{dispute}/status', [SuperDisputeController::class, 'updateStatus'])->name('disputes.status');
        Route::post('disputes/{dispute}/message', [SuperDisputeController::class, 'message'])->name('disputes.message');
        Route::post('disputes/{dispute}/finalize', [SuperDisputeController::class, 'finalize'])->name('disputes.finalize');
        Route::post('disputes/{dispute}/take', [SuperDisputeController::class, 'take'])->name('disputes.take');
        Route::post('disputes/{dispute}/release', [SuperDisputeController::class, 'release'])->name('disputes.release');
        Route::post('disputes/{dispute}/decide', [SuperDisputeController::class, 'decide'])->name('disputes.decide');


        Route::get('disputes', [SuperDisputeController::class, 'index'])->name('disputes.index');
Route::get('disputes/{dispute}', [SuperDisputeController::class, 'show'])->name('disputes.show');

Route::post('disputes/{dispute}/message', [SuperDisputeController::class, 'message'])->name('disputes.message');
Route::post('disputes/{dispute}/finalize', [SuperDisputeController::class, 'finalize'])->name('disputes.finalize');
Route::post('disputes/{dispute}/close', [SuperDisputeController::class, 'closeConversation'])->name('disputes.close');
        // BOOKINGS
        Route::get('bookings', [SuperBookingController::class, 'index'])->name('bookings.index');
        Route::get('bookings/{reservation}', [SuperBookingController::class, 'show'])->name('bookings.show');

        // TRANSACTIONS
        Route::get('transactions', [SuperTransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/{reservation}', [SuperTransactionController::class, 'show'])
            ->whereNumber('reservation')->name('transactions.show');
        Route::post('transactions/{reservation}/recompute', [SuperTransactionController::class, 'recompute'])
            ->whereNumber('reservation')->name('transactions.recompute');



            Route::get('staff-analytics', [\App\Http\Controllers\SuperAdmin\SuperStaffAnalyticsController::class, 'index'])
    ->name('staff_analytics.index');
        // SUPPORT
        Route::get('support/conversations', [SuperSupportConversationController::class, 'index'])->name('support.conversations.index');
        Route::get('support/conversations/{conversation}/search', [SuperSupportConversationController::class, 'search'])->name('support.conversations.search');
        Route::get('support/conversations/{conversation}', [SuperSupportConversationController::class, 'show'])->name('support.conversations.show');
        Route::post('support/conversations/{conversation}/message', [SuperSupportConversationController::class, 'storeMessage'])->name('support.conversations.message.store');
        Route::get('support/messages/latest', [SuperSupportConversationController::class, 'latest'])->name('support.messages.latest');
        Route::post('support/conversations/{conversation}/status', [SuperSupportConversationController::class, 'updateStatus'])->name('support.conversations.status');
        Route::post('support/conversations/{conversation}/assign-me', [SuperSupportConversationController::class, 'assignMe'])->name('support.conversations.assignMe');


        Route::post('support/conversations/{conversation}/request-manager',
  [SuperSupportConversationController::class, 'requestManager']
)->name('support.conversations.requestManager');

Route::post('support/conversations/{conversation}/manager-join',
  [SuperSupportConversationController::class, 'managerJoin']
)->name('support.conversations.managerJoin');

Route::post('support/conversations/{conversation}/manager-end',
  [SuperSupportConversationController::class, 'managerEnd']
)->name('support.conversations.managerEnd');




        // CATEGORIES
        Route::get('categories', [SuperCategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [SuperCategoryController::class, 'store'])->name('categories.store');
        Route::put('categories/{category}', [SuperCategoryController::class, 'update'])->name('categories.update');
        Route::post('categories/{category}/on', [SuperCategoryController::class, 'activate'])->name('categories.activate');
        Route::post('categories/{category}/off', [SuperCategoryController::class, 'deactivate'])->name('categories.deactivate');
        Route::delete('categories/{category}', [SuperCategoryController::class, 'destroy'])->name('categories.destroy');

        // COACHES
        Route::get('coaches', [SuperCoachController::class,'index'])->name('coaches.index');
        Route::post('coaches/{user}/approve', [SuperCoachController::class,'approve'])->name('coaches.approve');
        Route::post('coaches/{user}/reject', [SuperCoachController::class,'reject'])->name('coaches.reject');
        Route::delete('coaches/{user}', [SuperCoachController::class,'destroy'])->name('coaches.destroy');
        Route::post('coaches/{id}/restore', [SuperCoachController::class, 'restore'])->name('coaches.restore');
        Route::get('coaches/{user}', [SuperCoachController::class, 'show'])->name('coaches.show');

        // CLIENTS
        Route::get('clients', [SuperClientController::class, 'index'])->name('clients.index');
        Route::get('clients/{id}', [SuperClientController::class, 'show'])->whereNumber('id')->name('clients.show');
        Route::delete('clients/{user}', [SuperClientController::class, 'destroy'])->name('clients.destroy');
        Route::post('clients/{id}/restore', [SuperClientController::class, 'restore'])->whereNumber('id')->name('clients.restore');
   Route::get('/clients/{client}/stats', [SuperClientAnalytics::class, 'show'])
    ->name('clients.stats');
   Route::get('/coaches/{coach}/stats', [SuperCoachAnalytics::class, 'show'])
    ->name('coaches.stats');


        // SETTINGS (same structure)
        Route::get('settings', fn() => redirect()->route('superadmin.settings.trainer.edit'))->name('settings.index');
        Route::get('settings/trainer',  [SuperWebsiteSettingsController::class,'trainerEdit'])->name('settings.trainer.edit');
        Route::post('settings/trainer', [SuperWebsiteSettingsController::class,'trainerUpdate'])->name('settings.trainer.update');
        Route::get('settings/customer',  [SuperWebsiteSettingsController::class,'customerEdit'])->name('settings.customer.edit');
        Route::post('settings/customer', [SuperWebsiteSettingsController::class,'customerUpdate'])->name('settings.customer.update');
        Route::get('settings/appearance',  [SuperWebsiteSettingsController::class,'appearanceEdit'])->name('settings.appearance.edit');
        Route::post('settings/appearance', [SuperWebsiteSettingsController::class,'appearanceUpdate'])->name('settings.appearance.update');
        // ✅ DELETE single images (superadmin only)
Route::post('settings/trainer/default-cover/delete',
    [SuperWebsiteSettingsController::class, 'trainerDefaultCoverDelete']
)->name('settings.trainer.default_cover.delete');

Route::post('settings/appearance/search-bg/delete',
    [SuperWebsiteSettingsController::class, 'appearanceSearchBgDelete']
)->name('settings.appearance.search_bg.delete');

Route::post('settings/appearance/middle-banner/delete',
    [SuperWebsiteSettingsController::class, 'appearanceMiddleBannerDelete']
)->name('settings.appearance.middle_banner.delete');


Route::get('/security/locked-staff', [LockedStaffController::class, 'index'])->name('security.locked_staff');
    Route::post('/security/locked-staff/{user}/unlock', [LockedStaffController::class, 'unlock'])->name('security.locked_staff.unlock');
    Route::post('/security/locked-staff/{user}/lock', [LockedStaffController::class, 'lock'])->name('security.locked_staff.lock');

    Route::get('/security/logs', [AdminLogsController::class, 'index'])->name('security.logs');

    Route::get('/security/events', [SecurityEventsController::class, 'index'])->name('security.events');
    Route::post('/security/events/{event}/mark-reviewed', [SecurityEventsController::class, 'markReviewed'])->name('security.events.review');


        // SERVICES
        Route::get('services', [SuperServiceController::class, 'index'])->name('services.index');
        Route::get('services/requests', [SuperServiceController::class, 'requests'])->name('services.requests');
        Route::get('services/{id}', [SuperServiceController::class, 'show'])->whereNumber('id')->name('services.show');
        Route::post('services/{service}/approve', [SuperServiceController::class, 'approve'])->name('services.approve');
        Route::post('services/{service}/reject', [SuperServiceController::class, 'reject'])->name('services.reject');
        Route::post('services/{service}/toggle-active', [SuperServiceController::class, 'toggleActive'])->name('services.toggleActive');
        Route::delete('services/{service}', [SuperServiceController::class, 'destroy'])->name('services.destroy');
        Route::post('services/{id}/restore', [SuperServiceController::class, 'restore'])->whereNumber('id')->name('services.restore');
        Route::post('services/{service}/disable', [SuperServiceController::class, 'disable'])->name('services.disable');
        Route::post('services/{service}/enable', [SuperServiceController::class, 'enable'])->name('services.enable');


        Route::get('staff/{id}/webauthn', [StaffController::class, 'webauthnIndex'])
    ->whereNumber('id')
    ->name('staff.webauthn.index');

Route::delete('staff/{id}/webauthn/{credentialId}', [StaffController::class, 'webauthnDestroy'])
    ->whereNumber('id')
    ->name('staff.webauthn.destroy');

Route::post('staff/{id}/webauthn/reset', [StaffController::class, 'webauthnReset'])
    ->whereNumber('id')
    ->name('staff.webauthn.reset');

        Route::get('staff', [StaffController::class,'index'])->name('staff.index');

        Route::get('staff/create', [StaffController::class,'create'])->name('staff.create');
Route::get('staff/{user}/edit', [StaffController::class,'edit'])->name('staff.edit');
    Route::post('staff', [StaffController::class,'store'])->name('staff.store');
    Route::put('staff/{user}', [StaffController::class,'update'])->name('staff.update');
    


     Route::post('staff/{user}/resend-invite', [StaffController::class,'resendInvite'])
    ->name('staff.resendInvite');

    Route::delete('/staff-documents/{doc}', [StaffDocumentController::class,'destroy'])->name('staff_documents.destroy');


    Route::get('withdrawals', [SuperWithdrawalController::class, 'index'])
    ->name('withdrawals.index');
    // routes/web.php (inside your superadmin group)
Route::delete('staff/{id}', [StaffController::class, 'destroy'])->whereNumber('id')->name('staff.destroy');
Route::post('staff/{id}/restore', [StaffController::class, 'restore'])->whereNumber('id')->name('staff.restore');
Route::get('staff/{id}/info', [StaffController::class, 'info'])->whereNumber('id')->name('staff.info');




Route::get('email-subscriptions', [EmailSubscriptionController::class, 'index'])
    ->name('email-subscriptions.index');

Route::patch('email-subscriptions/{subscriber}/toggle', [EmailSubscriptionController::class, 'toggle'])
    ->name('email-subscriptions.toggle');

Route::delete('email-subscriptions/{subscriber}', [EmailSubscriptionController::class, 'destroy'])
    ->name('email-subscriptions.destroy');

    Route::get('email-subscriptions/compose', [EmailSubscriptionController::class, 'compose'])
  ->name('email-subscriptions.compose');

Route::post('email-subscriptions/send', [EmailSubscriptionController::class, 'sendToAll'])
  ->name('email-subscriptions.send');


  Route::get('support/questions', [\App\Http\Controllers\SuperAdmin\SuperSupportQuestionController::class, 'index'])
  ->name('support.questions.index');

Route::get('support/questions/{question}', [\App\Http\Controllers\SuperAdmin\SuperSupportQuestionController::class, 'show'])
  ->name('support.questions.show');

  // SUPERADMIN - Agent Working Hours (Charts + Timeline)


  


  Route::get('teams', [StaffTeamController::class, 'index'])->name('teams.index');
Route::get('teams/create', [StaffTeamController::class, 'create'])->name('teams.create');
Route::post('teams', [StaffTeamController::class, 'store'])->name('teams.store');
Route::get('teams/{team}/edit', [StaffTeamController::class, 'edit'])->name('teams.edit');
Route::put('teams/{team}', [StaffTeamController::class, 'update'])->name('teams.update');


Route::prefix('staff-chat')->name('staff_chat.')->group(function () {
  Route::get('/', [\App\Http\Controllers\SuperAdmin\SuperStaffChatController::class, 'index'])
    ->name('index');

  Route::get('/room/{room}', [\App\Http\Controllers\SuperAdmin\SuperStaffChatController::class, 'show'])
    ->whereNumber('room')
    ->name('show');

  Route::post('/room/{room}/send', [\App\Http\Controllers\SuperAdmin\SuperStaffChatController::class, 'send'])
    ->whereNumber('room')
    ->name('send');

  Route::get('/unreads', [\App\Http\Controllers\SuperAdmin\SuperStaffChatController::class, 'unreads'])
    ->name('unreads');

  Route::post('/room/{room}/read', [\App\Http\Controllers\SuperAdmin\SuperStaffChatController::class, 'markRead'])
    ->whereNumber('room')
    ->name('read');
      Route::post('/dm/start', [\App\Http\Controllers\SuperAdmin\SuperStaffChatController::class, 'startDm'])
    ->name('dm.start');

  Route::get('/room/{room}/latest', [\App\Http\Controllers\SuperAdmin\SuperStaffChatController::class, 'latest'])
    ->whereNumber('room')
    ->name('messages.latest');

  // Optional: announcement posting route (you already had something similar)
  Route::post('/room/{room}/announcement', [\App\Http\Controllers\SuperAdmin\SuperStaffChatController::class, 'storeAnnouncement'])
    ->whereNumber('room')
    ->name('announcement.store');

});



// =============================
// SUPERADMIN: Leave Review
// - reviews manager requests
// - can also review admin requests (optional)
// =============================
Route::prefix('support/leave')->name('support.leave.')->group(function () {

  Route::get('/review', [SuperSupportLeaveController::class, 'review'])
    ->name('review');

  Route::post('/decide/{request}', [SuperSupportLeaveController::class, 'decide'])
    ->whereNumber('request')
    ->name('decide');

  Route::get('/review-request-file/{file}', [SuperSupportLeaveController::class, 'downloadReviewRequestFile'])
    ->whereNumber('file')
    ->name('review_request_file.download');

    Route::get('/request/{request}', [SuperSupportLeaveController::class, 'show'])
  ->whereNumber('request')
  ->name('show');
});




    Route::delete('teams/{team}', [StaffTeamController::class, 'destroy'])
  ->name('teams.destroy');

Route::post('teams/{id}/restore', [StaffTeamController::class, 'restore'])
  ->name('teams.restore');

Route::get('analytics', [\App\Http\Controllers\SuperAdmin\SuperAnalyticsController::class, 'index'])
    ->name('analytics.index');

  Route::get('/dm', [SuperadminDmController::class, 'index'])->name('dm.index');
    Route::get('/dm/{thread}', [SuperadminDmController::class, 'show'])->name('dm.show');


   

    Route::get('support/status-analytics', [\App\Http\Controllers\SuperAdmin\SupportAgentStatusAnalyticsSuperadminController::class, 'index'])
        ->name('support.status_analytics.index');

    Route::get('support/status-analytics/data', [\App\Http\Controllers\SuperAdmin\SupportAgentStatusAnalyticsSuperadminController::class, 'data'])
        ->name('support.status_analytics.data');
    });




  





    Route::middleware('guest')->group(function () {
    Route::get('/staff/invite/{token}', [StaffInviteController::class, 'show'])
        ->name('staff.invite.show');

    Route::post('/staff/invite/{token}', [StaffInviteController::class, 'store'])
        ->name('staff.invite.store');
});




