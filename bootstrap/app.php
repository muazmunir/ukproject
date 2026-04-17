<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

use App\Http\Middleware\UseUserTimezone;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ShareGTLang;
use App\Http\Middleware\EnsureCoachApproved;
use App\Http\Middleware\EnsureCoachPayoutReady;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
    )
    ->withCommands([
        __DIR__ . '/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
            'api/stripe/webhook',

            'admin/webauthn/register/options',
            'admin/webauthn/register/store',
            'admin/webauthn/verify/options',
            'admin/webauthn/verify/store',
        ]);

        $middleware->alias([
            'tz.user' => UseUserTimezone::class,
            'locale' => SetLocale::class,
            'gt.share' => ShareGTLang::class,

            'admin.access' => \App\Http\Middleware\AdminOrManager::class,
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'superadmin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'track.visitor' => \App\Http\Middleware\TrackVisitorActivity::class,

            'coach.approved' => EnsureCoachApproved::class,
            'coach.kyc.overlay' => \App\Http\Middleware\CoachKycOverlayGate::class,
            'admin.not_locked' => \App\Http\Middleware\EnsureAdminNotHardLocked::class,
            'admin.softlock' => \App\Http\Middleware\AdminSoftLockMiddleware::class,
            'coach.payout.ready' => EnsureCoachPayoutReady::class,

            'admin.guest' => \App\Http\Middleware\RedirectIfAdminAuthenticated::class,
            'enforce.shift' => \App\Http\Middleware\EnforceShift::class,
            'admin.passkey' => \App\Http\Middleware\EnsureAdminPasskeyVerified::class,

            'staff.lock' => \App\Http\Middleware\BlockStaffOutsideAdmin::class,

            'acting' => \App\Http\Middleware\ActingRole::class,
            'coach.lock' => \App\Http\Middleware\CoachLock::class,
            'coach.onboarding' => \App\Http\Middleware\CoachOnboardingGate::class,
            'redirect.if.coach' => \App\Http\Middleware\RedirectIfActingCoach::class,
        ]);

        $middleware->prependToGroup('web', [
            UseUserTimezone::class,
        ]);

        $middleware->appendToGroup('web', [
            SetLocale::class,
            ShareGTLang::class,
            \App\Http\Middleware\TrackVisitorActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->create();