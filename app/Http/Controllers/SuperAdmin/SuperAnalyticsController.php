<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\CoachPayout;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\ReservationReview;
use App\Models\Users;
use App\Models\Dispute;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SuperAnalyticsController extends Controller
{
    public function index(Request $request)
    {
      $reportTimezone = (string) $request->query('tz', auth()->user()?->timezone ?? 'UTC');

if (!in_array($reportTimezone, timezone_identifiers_list(), true)) {
    $reportTimezone = 'UTC';
}

[$dateFrom, $dateTo, $range, $periodLabel, $bucketMode] = $this->resolveRange($request, $reportTimezone);

        $sort               = strtolower((string) $request->query('sort', 'desc'));
        $serviceFilter      = trim((string) $request->query('service', ''));
        $categoryFilter     = trim((string) $request->query('category', ''));
        $coachFilter        = trim((string) $request->query('coach', ''));
        $clientFilter       = trim((string) $request->query('client', ''));
        $search             = trim((string) $request->query('q', ''));
        $ratingSort         = strtolower((string) $request->query('rating_sort', 'desc'));
        $paymentProvider    = strtolower((string) $request->query('provider', ''));
        $fundingFilter      = strtolower((string) $request->query('funding_status', ''));
        $settlementFilter   = strtolower((string) $request->query('settlement_status', ''));
        $reservationStatus  = strtolower((string) $request->query('reservation_status', ''));
        $payoutStatus       = strtolower((string) $request->query('payout_status', ''));
        $refundStatus       = strtolower((string) $request->query('refund_status', ''));
        $reviewRole         = strtolower((string) $request->query('review_role', 'coach'));

        $sort = in_array($sort, ['asc', 'desc'], true) ? $sort : 'desc';
        $ratingSort = in_array($ratingSort, ['asc', 'desc'], true) ? $ratingSort : 'desc';

        $reservationDateExpr = 'COALESCE(reservations.completed_at, reservations.cancelled_at, reservations.created_at)';
        $paymentDateExpr     = 'COALESCE(payments.succeeded_at, payments.created_at)';
        $refundDateExpr      = 'COALESCE(refunds.processed_at, refunds.requested_at, refunds.created_at)';
        $payoutDateExpr      = 'COALESCE(coach_payouts.paid_at, coach_payouts.created_at)';
        $walletDateExpr      = 'COALESCE(wallet_transactions.created_at, wallet_transactions.updated_at)';
        $analyticsDateExpr   = 'analytics_events.created_at';
        $usersDateExpr       = 'users.created_at';
        $reviewsDateExpr     = 'reservation_reviews.created_at';

        $fmt = fn (int|float|null $minor) => '$' . number_format(((int) $minor) / 100, 2);
        $pct = fn ($num, $den) => $den > 0 ? round(($num / $den) * 100, 2) : 0.0;

        /*
        |--------------------------------------------------------------------------
        | FILTERABLE BASE QUERIES
        |--------------------------------------------------------------------------
        */

        $reservationsBase = Reservation::query()
            ->leftJoin('services', 'services.id', '=', 'reservations.service_id')
            ->leftJoin('users as clients', 'clients.id', '=', 'reservations.client_id')
            ->leftJoin('users as coaches', 'coaches.id', '=', 'reservations.coach_id')
            ->when($dateFrom, fn ($q) => $q->whereRaw("$reservationDateExpr >= ?", [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereRaw("$reservationDateExpr <= ?", [$dateTo]))
            ->when($serviceFilter !== '', function ($q) use ($serviceFilter) {
                $q->where(function ($w) use ($serviceFilter) {
                    $w->where('services.title', 'like', "%{$serviceFilter}%")
                      ->orWhere('reservations.service_title_snapshot', 'like', "%{$serviceFilter}%");
                });
            })
            ->when($categoryFilter !== '', function ($q) use ($categoryFilter) {
                $this->applyCategoryFilter($q, $categoryFilter);
            })
            ->when($coachFilter !== '', function ($q) use ($coachFilter) {
                $q->where(function ($w) use ($coachFilter) {
                    $w->whereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) like ?", ["%{$coachFilter}%"])
                      ->orWhere('coaches.email', 'like', "%{$coachFilter}%");
                });
            })
            ->when($clientFilter !== '', function ($q) use ($clientFilter) {
                $q->where(function ($w) use ($clientFilter) {
                    $w->whereRaw("TRIM(CONCAT(COALESCE(clients.first_name,''), ' ', COALESCE(clients.last_name,''))) like ?", ["%{$clientFilter}%"])
                      ->orWhere('clients.email', 'like', "%{$clientFilter}%");
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    if (ctype_digit($search)) {
                        $w->orWhere('reservations.id', (int) $search)
                          ->orWhere('reservations.client_id', (int) $search)
                          ->orWhere('reservations.coach_id', (int) $search)
                          ->orWhere('reservations.service_id', (int) $search);
                    }

                    $w->orWhere('reservations.status', 'like', "%{$search}%")
                      ->orWhere('reservations.payment_status', 'like', "%{$search}%")
                      ->orWhere('reservations.settlement_status', 'like', "%{$search}%")
                      ->orWhere('reservations.refund_status', 'like', "%{$search}%")
                      ->orWhere('reservations.funding_status', 'like', "%{$search}%")
                      ->orWhere('services.title', 'like', "%{$search}%")
                      ->orWhere('reservations.service_title_snapshot', 'like', "%{$search}%")
                      ->orWhereRaw("TRIM(CONCAT(COALESCE(clients.first_name,''), ' ', COALESCE(clients.last_name,''))) like ?", ["%{$search}%"])
                      ->orWhereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) like ?", ["%{$search}%"])
                      ->orWhere('clients.email', 'like', "%{$search}%")
                      ->orWhere('coaches.email', 'like', "%{$search}%");
                });
            })
            ->when($fundingFilter !== '', fn ($q) => $q->where('reservations.funding_status', $fundingFilter))
            ->when($settlementFilter !== '', fn ($q) => $q->where('reservations.settlement_status', $settlementFilter))
            ->when($reservationStatus !== '', fn ($q) => $q->where('reservations.status', $reservationStatus));

        $paymentsBase = Payment::query()
            ->leftJoin('reservations', 'reservations.id', '=', 'payments.reservation_id')
            ->leftJoin('services', 'services.id', '=', 'reservations.service_id')
            ->leftJoin('users as clients', 'clients.id', '=', 'reservations.client_id')
            ->leftJoin('users as coaches', 'coaches.id', '=', 'reservations.coach_id')
            ->when($dateFrom, fn ($q) => $q->whereRaw("$paymentDateExpr >= ?", [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereRaw("$paymentDateExpr <= ?", [$dateTo]))
            ->when($paymentProvider !== '', fn ($q) => $q->where('payments.provider', $paymentProvider))
            ->when($serviceFilter !== '', function ($q) use ($serviceFilter) {
                $q->where(function ($w) use ($serviceFilter) {
                    $w->where('services.title', 'like', "%{$serviceFilter}%")
                      ->orWhere('reservations.service_title_snapshot', 'like', "%{$serviceFilter}%");
                });
            })
            ->when($categoryFilter !== '', function ($q) use ($categoryFilter) {
                $this->applyCategoryFilter($q, $categoryFilter);
            })
            ->when($coachFilter !== '', function ($q) use ($coachFilter) {
                $q->where(function ($w) use ($coachFilter) {
                    $w->whereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) like ?", ["%{$coachFilter}%"])
                      ->orWhere('coaches.email', 'like', "%{$coachFilter}%");
                });
            })
            ->when($clientFilter !== '', function ($q) use ($clientFilter) {
                $q->where(function ($w) use ($clientFilter) {
                    $w->whereRaw("TRIM(CONCAT(COALESCE(clients.first_name,''), ' ', COALESCE(clients.last_name,''))) like ?", ["%{$clientFilter}%"])
                      ->orWhere('clients.email', 'like', "%{$clientFilter}%");
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    if (ctype_digit($search)) {
                        $w->orWhere('payments.id', (int) $search)
                          ->orWhere('payments.reservation_id', (int) $search);
                    }

                    $w->orWhere('payments.provider', 'like', "%{$search}%")
                      ->orWhere('payments.method', 'like', "%{$search}%")
                      ->orWhere('payments.status', 'like', "%{$search}%")
                      ->orWhere('payments.refund_status', 'like', "%{$search}%")
                      ->orWhere('services.title', 'like', "%{$search}%")
                      ->orWhere('reservations.service_title_snapshot', 'like', "%{$search}%")
                      ->orWhereRaw("TRIM(CONCAT(COALESCE(clients.first_name,''), ' ', COALESCE(clients.last_name,''))) like ?", ["%{$search}%"])
                      ->orWhereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) like ?", ["%{$search}%"]);
                });
            });

        $refundsBase = Refund::query()
            ->leftJoin('reservations', 'reservations.id', '=', 'refunds.reservation_id')
            ->leftJoin('services', 'services.id', '=', 'reservations.service_id')
            ->leftJoin('users as clients', 'clients.id', '=', 'reservations.client_id')
            ->leftJoin('users as coaches', 'coaches.id', '=', 'reservations.coach_id')
            ->when($dateFrom, fn ($q) => $q->whereRaw("$refundDateExpr >= ?", [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereRaw("$refundDateExpr <= ?", [$dateTo]))
            ->when($refundStatus !== '', fn ($q) => $q->where('refunds.status', $refundStatus))
            ->when($serviceFilter !== '', function ($q) use ($serviceFilter) {
                $q->where(function ($w) use ($serviceFilter) {
                    $w->where('services.title', 'like', "%{$serviceFilter}%")
                      ->orWhere('reservations.service_title_snapshot', 'like', "%{$serviceFilter}%");
                });
            })
            ->when($categoryFilter !== '', function ($q) use ($categoryFilter) {
                $this->applyCategoryFilter($q, $categoryFilter);
            })
            ->when($coachFilter !== '', function ($q) use ($coachFilter) {
                $q->where(function ($w) use ($coachFilter) {
                    $w->whereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) like ?", ["%{$coachFilter}%"])
                      ->orWhere('coaches.email', 'like', "%{$coachFilter}%");
                });
            })
            ->when($clientFilter !== '', function ($q) use ($clientFilter) {
                $q->where(function ($w) use ($clientFilter) {
                    $w->whereRaw("TRIM(CONCAT(COALESCE(clients.first_name,''), ' ', COALESCE(clients.last_name,''))) like ?", ["%{$clientFilter}%"])
                      ->orWhere('clients.email', 'like', "%{$clientFilter}%");
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    if (ctype_digit($search)) {
                        $w->orWhere('refunds.id', (int) $search)
                          ->orWhere('refunds.reservation_id', (int) $search);
                    }

                    $w->orWhere('refunds.provider', 'like', "%{$search}%")
                      ->orWhere('refunds.method', 'like', "%{$search}%")
                      ->orWhere('refunds.status', 'like', "%{$search}%")
                      ->orWhere('refunds.wallet_status', 'like', "%{$search}%")
                      ->orWhere('refunds.external_status', 'like', "%{$search}%")
                      ->orWhere('services.title', 'like', "%{$search}%")
                      ->orWhere('reservations.service_title_snapshot', 'like', "%{$search}%")
                      ->orWhereRaw("TRIM(CONCAT(COALESCE(clients.first_name,''), ' ', COALESCE(clients.last_name,''))) like ?", ["%{$search}%"])
                      ->orWhereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) like ?", ["%{$search}%"]);
                });
            });

        $payoutsBase = CoachPayout::query()
            ->leftJoin('coach_profiles', 'coach_profiles.id', '=', 'coach_payouts.coach_profile_id')
            ->leftJoin('users as coaches', 'coaches.id', '=', 'coach_profiles.user_id')
            ->when($dateFrom, fn ($q) => $q->whereRaw("$payoutDateExpr >= ?", [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereRaw("$payoutDateExpr <= ?", [$dateTo]))
            ->when($coachFilter !== '', function ($q) use ($coachFilter) {
                $q->where(function ($w) use ($coachFilter) {
                    $w->whereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) like ?", ["%{$coachFilter}%"])
                      ->orWhere('coaches.email', 'like', "%{$coachFilter}%");
                });
            })
            ->when($payoutStatus !== '', fn ($q) => $q->where('coach_payouts.status', $payoutStatus))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    if (ctype_digit($search)) {
                        $w->orWhere('coach_payouts.id', (int) $search);
                    }

                    $w->orWhere('coach_payouts.provider', 'like', "%{$search}%")
                      ->orWhere('coach_payouts.status', 'like', "%{$search}%")
                      ->orWhere('coach_payouts.provider_transfer_id', 'like', "%{$search}%")
                      ->orWhere('coach_payouts.provider_payout_id', 'like', "%{$search}%")
                      ->orWhereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) like ?", ["%{$search}%"]);
                });
            });

        $walletBase = WalletTransaction::query()
            ->when($dateFrom, fn ($q) => $q->whereRaw("$walletDateExpr >= ?", [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereRaw("$walletDateExpr <= ?", [$dateTo]));

        $analyticsEventsBase = AnalyticsEvent::query()
            ->when($dateFrom, fn ($q) => $q->whereRaw("$analyticsDateExpr >= ?", [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereRaw("$analyticsDateExpr <= ?", [$dateTo]));

        $usersBase = Users::query()
            ->when($dateFrom, fn ($q) => $q->whereRaw("$usersDateExpr >= ?", [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereRaw("$usersDateExpr <= ?", [$dateTo]));

        $reviewsBase = ReservationReview::query()
            ->leftJoin('reservations', 'reservations.id', '=', 'reservation_reviews.reservation_id')
            ->leftJoin('services', 'services.id', '=', 'reservations.service_id')
            ->leftJoin('users as clients', 'clients.id', '=', 'reservations.client_id')
            ->leftJoin('users as coaches', 'coaches.id', '=', 'reservations.coach_id')
            ->leftJoin('users as reviewees', 'reviewees.id', '=', 'reservation_reviews.reviewee_id')
            ->when($dateFrom, fn ($q) => $q->whereRaw("$reviewsDateExpr >= ?", [$dateFrom]))
            ->when($dateTo, fn ($q) => $q->whereRaw("$reviewsDateExpr <= ?", [$dateTo]))
            ->when($reviewRole !== '', fn ($q) => $q->where('reservation_reviews.reviewee_role', $reviewRole))
            ->when($serviceFilter !== '', function ($q) use ($serviceFilter) {
                $q->where(function ($w) use ($serviceFilter) {
                    $w->where('services.title', 'like', "%{$serviceFilter}%")
                      ->orWhere('reservations.service_title_snapshot', 'like', "%{$serviceFilter}%");
                });
            })
            ->when($categoryFilter !== '', function ($q) use ($categoryFilter) {
                $this->applyCategoryFilter($q, $categoryFilter);
            })
            ->when($coachFilter !== '', function ($q) use ($coachFilter) {
                $q->where(function ($w) use ($coachFilter) {
                    $w->whereRaw("TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))) like ?", ["%{$coachFilter}%"])
                      ->orWhereRaw("TRIM(CONCAT(COALESCE(reviewees.first_name,''), ' ', COALESCE(reviewees.last_name,''))) like ?", ["%{$coachFilter}%"]);
                });
            })
            ->when($clientFilter !== '', function ($q) use ($clientFilter) {
                $q->where(function ($w) use ($clientFilter) {
                    $w->whereRaw("TRIM(CONCAT(COALESCE(clients.first_name,''), ' ', COALESCE(clients.last_name,''))) like ?", ["%{$clientFilter}%"])
                      ->orWhere('clients.email', 'like', "%{$clientFilter}%");
                });
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    if (ctype_digit($search)) {
                        $w->orWhere('reservation_reviews.id', (int) $search)
                          ->orWhere('reservation_reviews.reservation_id', (int) $search)
                          ->orWhere('reservation_reviews.reviewee_id', (int) $search);
                    }

                $w->orWhere('reservation_reviews.reviewee_role', 'like', "%{$search}%")
  ->orWhere('reservation_reviews.description', 'like', "%{$search}%")
  ->orWhere('services.title', 'like', "%{$search}%")
  ->orWhereRaw("TRIM(CONCAT(COALESCE(reviewees.first_name,''), ' ', COALESCE(reviewees.last_name,''))) like ?", ["%{$search}%"]);
                });
            });

        $successfulPayments = (clone $paymentsBase)->where('payments.status', 'succeeded');
        $paidReservations   = (clone $reservationsBase)->where('reservations.payment_status', 'paid');

        /*
        |--------------------------------------------------------------------------
        | BUSINESS MONEY KPIS (OUR POCKET / PLATFORM)
        |--------------------------------------------------------------------------
        */

        $paymentCount = (int) (clone $successfulPayments)->count();

        $grossInflowMinor = (int) (clone $successfulPayments)
            ->sum(DB::raw('COALESCE(payments.amount_total,0)'));

        $externalInflowMinor = (int) (clone $successfulPayments)
            ->whereIn('payments.provider', ['stripe', 'paypal'])
            ->sum(DB::raw('COALESCE(payments.amount_total,0)'));

        $walletInflowMinor = (int) (clone $successfulPayments)
            ->where('payments.provider', 'wallet')
            ->sum(DB::raw('COALESCE(payments.amount_total,0)'));

        $serviceSalesMinor = (int) (clone $successfulPayments)
            ->sum(DB::raw('COALESCE(payments.service_subtotal_minor,0)'));

        $clientFeeCollectedMinor = (int) (clone $successfulPayments)
            ->sum(DB::raw('COALESCE(payments.client_fee_minor,0)'));

        $coachCommissionCollectedMinor = (int) (clone $paidReservations)
            ->sum(DB::raw('COALESCE(reservations.coach_commission_minor, COALESCE(reservations.coach_fee_minor,0))'));

       $platformEarnedMinor = (int) (clone $paidReservations)
    ->sum(DB::raw('COALESCE(reservations.platform_earned_minor,0) + COALESCE(reservations.coach_penalty_minor,0)'));

        $platformFeeRefundedMinor = (int) (clone $paidReservations)
            ->sum(DB::raw('COALESCE(reservations.platform_fee_refunded_minor,0)'));

        $platformNetEarnedMinor = $platformEarnedMinor;

        $refundOutflowMinor = (int) (clone $refundsBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_original_minor,0)'));

        $netBusinessCashMinor = $grossInflowMinor - $refundOutflowMinor;
        $businessRetentionRate = $pct($platformNetEarnedMinor, max($grossInflowMinor, 1));

        $businessMoneyKpis = [
            [
                'key' => 'gross_inflow',
                'label' => 'Gross Inflow',
                'value_minor' => $grossInflowMinor,
                'meta' => 'All successful money received',
            ],
            [
                'key' => 'platform_earned',
                'label' => 'Platform Earned',
                'value_minor' => $platformEarnedMinor,
                'meta' => 'Money earned by the business',
                'highlight' => true,
            ],
            [
                'key' => 'platform_net_earned',
                'label' => 'Platform Net Earned',
                'value_minor' => $platformNetEarnedMinor,
                'meta' => 'Net platform income after business rules',
                'highlight' => true,
            ],
            [
                'key' => 'refund_outflow',
                'label' => 'Refund Cash Outflow',
                'value_minor' => $refundOutflowMinor,
                'meta' => 'Cash sent back to original payment method',
            ],
            [
                'key' => 'net_business_cash',
                'label' => 'Net Business Cash',
                'value_minor' => $netBusinessCashMinor,
                'meta' => 'What remained in cash after refund outflow',
                'highlight' => true,
            ],
            [
                'key' => 'client_fees',
                'label' => 'Client Fees Collected',
                'value_minor' => $clientFeeCollectedMinor,
                'meta' => 'Fees collected from customers',
            ],
            [
                'key' => 'coach_commission',
                'label' => 'Coach Commission Collected',
                'value_minor' => $coachCommissionCollectedMinor,
                'meta' => 'Commission retained from coach side',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | INCOMING / OUTGOING
        |--------------------------------------------------------------------------
        */

        $refundSucceededCount  = (int) (clone $refundsBase)->where('refunds.status', 'succeeded')->count();
        $refundPartialCount    = (int) (clone $refundsBase)->where('refunds.status', 'partial')->count();
        $refundFailedCount     = (int) (clone $refundsBase)->where('refunds.status', 'failed')->count();
        $refundProcessingCount = (int) (clone $refundsBase)->where('refunds.status', 'processing')->count();

        $refundSucceededMinor = (int) (clone $refundsBase)
            ->where('refunds.status', 'succeeded')
            ->sum(DB::raw('COALESCE(refunds.refunded_to_wallet_minor,0) + COALESCE(refunds.refunded_to_original_minor,0)'));

        $refundPartialMinor = (int) (clone $refundsBase)
            ->where('refunds.status', 'partial')
            ->sum(DB::raw('COALESCE(refunds.refunded_to_wallet_minor,0) + COALESCE(refunds.refunded_to_original_minor,0)'));

        $totalRefundedMinor = (int) (clone $refundsBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_wallet_minor,0) + COALESCE(refunds.refunded_to_original_minor,0)'));

        $refundWalletMinor = (int) (clone $refundsBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_wallet_minor,0)'));

        $refundOriginalMinor = (int) (clone $refundsBase)
            ->whereIn('refunds.status', ['succeeded', 'partial'])
            ->sum(DB::raw('COALESCE(refunds.refunded_to_original_minor,0)'));

        $refundProcessingMinor = (int) (clone $refundsBase)
            ->where('refunds.status', 'processing')
            ->sum(DB::raw('COALESCE(refunds.actual_amount_minor,0)'));

        $coachReleasedMinor = (int) (clone $paidReservations)
            ->sum(DB::raw('COALESCE(reservations.coach_earned_minor,0)'));

        $coachCompMinor = (int) (clone $paidReservations)
            ->sum(DB::raw('COALESCE(reservations.coach_comp_minor,0)'));

        $coachPenaltyMinor = (int) (clone $paidReservations)
            ->sum(DB::raw('COALESCE(reservations.coach_penalty_minor,0)'));

        $coachNetBenefitMinor = $coachReleasedMinor + $coachCompMinor - $coachPenaltyMinor;

        $clientPenaltyMinor = (int) (clone $paidReservations)
            ->sum(DB::raw('COALESCE(reservations.client_penalty_minor,0)'));

        $incomingOutgoing = [
            'external_inflow_minor'      => $externalInflowMinor,
            'wallet_inflow_minor'        => $walletInflowMinor,
            'gross_inflow_minor'         => $grossInflowMinor,
            'refund_wallet_minor'        => $refundWalletMinor,
            'refund_original_minor'      => $refundOriginalMinor,
            'refund_total_minor'         => $totalRefundedMinor,
            'coach_payout_obligation'    => $coachNetBenefitMinor,
            'platform_net_earned_minor'  => $platformNetEarnedMinor,
        ];

        /*
        |--------------------------------------------------------------------------
        | OPERATIONS / BOOKINGS
        |--------------------------------------------------------------------------
        */

        $reservationsSummary = (clone $reservationsBase)
            ->select([])
            ->selectRaw("
                COUNT(reservations.id) as reservations_count,
                SUM(COALESCE(reservations.subtotal_minor,0)) as subtotal_minor,
                SUM(COALESCE(reservations.fees_minor,0)) as fees_minor,
                SUM(COALESCE(reservations.total_minor,0)) as total_minor,
                SUM(COALESCE(reservations.wallet_platform_credit_used_minor,0)) as wallet_used_minor,
                SUM(COALESCE(reservations.payable_minor,0)) as payable_minor
            ")
            ->first();

        $reservationsCount          = (int) ($reservationsSummary->reservations_count ?? 0);
        $reservationSubtotalMinor   = (int) ($reservationsSummary->subtotal_minor ?? 0);
        $reservationFeesMinor       = (int) ($reservationsSummary->fees_minor ?? 0);
        $reservationTotalMinor      = (int) ($reservationsSummary->total_minor ?? 0);
        $reservationWalletUsedMinor = (int) ($reservationsSummary->wallet_used_minor ?? 0);
        $reservationPayableMinor    = (int) ($reservationsSummary->payable_minor ?? 0);

        $completedCount = (int) (clone $reservationsBase)
            ->whereNotNull('reservations.completed_at')
            ->count();

        $cancelledCount = (int) (clone $reservationsBase)
            ->whereIn('reservations.status', ['cancelled', 'canceled'])
            ->count();

        $noShowCount = (int) (clone $reservationsBase)
            ->whereIn('reservations.status', ['no_show', 'no_show_coach', 'no_show_client', 'no_show_both'])
            ->count();

        $paidReservationsCount = (int) (clone $paidReservations)->count();

        /*
        |--------------------------------------------------------------------------
        | TRAFFIC / FUNNEL (REWORDED)
        |--------------------------------------------------------------------------
        */

        $siteVisitCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'site_visit')
            ->count();

        $uniqueVisitorCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'site_visit')
            ->whereNotNull('visitor_token')
            ->distinct('visitor_token')
            ->count('visitor_token');

        $bookingPageVisitCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'booking_page_visit')
            ->count();

        $walletCheckoutStartedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'wallet_checkout_started')
            ->count();

        $stripeCheckoutStartedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'stripe_checkout_intent_started')
            ->count();

        $paypalCheckoutStartedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'paypal_checkout_started')
            ->count();

        $checkoutStartedCount = $walletCheckoutStartedCount + $stripeCheckoutStartedCount + $paypalCheckoutStartedCount;

        $bookingPaidEventCount = (int) (clone $analyticsEventsBase)
            ->whereIn('type', [
                'booking_paid_wallet_only',
                'stripe_payment_succeeded',
                'paypal_payment_succeeded',
            ])
            ->count();

        $signupCreatedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'signup_created')
            ->count();

        $signupVerifiedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'signup_verified')
            ->count();

        $clientSignupCreatedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'client_signup_created')
            ->count();

        $coachSignupCreatedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'coach_signup_created')
            ->count();

        $clientSignupVerifiedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'client_signup_verified')
            ->count();

        $coachSignupVerifiedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'coach_signup_verified')
            ->count();

        $coachApplicationSubmittedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'coach_application_submitted')
            ->count();

        $otpResentCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'otp_resent')
            ->count();

        $otpFailedCount = (int) (clone $analyticsEventsBase)
            ->where('type', 'otp_verify_failed')
            ->count();

        $trafficAndConversion = [
            'visitors'                    => $uniqueVisitorCount,
            'site_visits'                 => $siteVisitCount,
            'booking_page_visits'         => $bookingPageVisitCount,
            'checkout_started'            => $checkoutStartedCount,
            'paid_bookings'               => $bookingPaidEventCount,
            'signup_created'              => $signupCreatedCount,
            'signup_verified'             => $signupVerifiedCount,
            'visit_to_signup_rate'        => $pct($signupCreatedCount, $uniqueVisitorCount),
            'visit_to_booking_page_rate'  => $pct($bookingPageVisitCount, $uniqueVisitorCount),
            'checkout_to_paid_rate'       => $pct($bookingPaidEventCount, $checkoutStartedCount),
            'visit_to_paid_booking_rate'  => $pct($bookingPaidEventCount, $uniqueVisitorCount),
            'signup_to_verification_rate' => $pct($signupVerifiedCount, $signupCreatedCount),
        ];

        /*
        |--------------------------------------------------------------------------
        | REVIEWS / COACH QUALITY
        |--------------------------------------------------------------------------
        */

        $reviewCount = (int) (clone $reviewsBase)->count();

        $coachReviewCount = (int) (clone $reviewsBase)
            ->where('reservation_reviews.reviewee_role', 'coach')
            ->count();

        $averageCoachRating = (float) round(
            (clone $reviewsBase)
                ->where('reservation_reviews.reviewee_role', 'coach')
                ->avg('reservation_reviews.stars') ?? 0,
            2
        );

        $ratingBreakdown = (clone $reviewsBase)
            ->select([])
            ->selectRaw("
                reservation_reviews.stars,
                COUNT(reservation_reviews.id) as review_count
            ")
            ->where('reservation_reviews.reviewee_role', 'coach')
            ->groupBy('reservation_reviews.stars')
            ->orderByDesc('reservation_reviews.stars')
            ->get();

        $topRatedCoaches = Users::query()
            ->whereNotNull('coach_rating_avg')
            ->where('coach_rating_count', '>', 0)
            ->selectRaw("
                id,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))), ''), 'Unknown Coach') as coach_name,
                coach_rating_avg,
                coach_rating_count
            ")
            ->orderByDesc('coach_rating_avg')
            ->orderByDesc('coach_rating_count')
            ->limit(50)
            ->get();

        $worstCoaches = Users::query()
            ->whereNotNull('coach_rating_avg')
            ->where('coach_rating_count', '>', 0)
            ->selectRaw("
                id,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))), ''), 'Unknown Coach') as coach_name,
                coach_rating_avg,
                coach_rating_count
            ")
            ->orderBy('coach_rating_avg')
            ->orderByDesc('coach_rating_count')
            ->limit(100)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | PAYMENT / FUNDING / SETTLEMENT BREAKDOWNS
        |--------------------------------------------------------------------------
        */

        $providerBase = (clone $successfulPayments)
            ->select([])
            ->selectRaw("
                COALESCE(payments.provider, 'unknown') as provider,
                payments.id,
                COALESCE(payments.amount_total, 0) as amount_total
            ");

        $providerBreakdown = DB::query()
            ->fromSub($providerBase, 'p')
            ->selectRaw("
                p.provider,
                COUNT(p.id) as tx_count,
                SUM(p.amount_total) as amount_minor
            ")
            ->groupBy('p.provider')
            ->orderByRaw("amount_minor {$sort}")
            ->get();

        $paymentMethodBase = (clone $successfulPayments)
            ->select([])
            ->selectRaw("
                COALESCE(NULLIF(payments.method, ''), payments.provider, 'unknown') as payment_method,
                payments.id,
                COALESCE(payments.amount_total, 0) as amount_total
            ");

        $paymentMethodBreakdown = DB::query()
            ->fromSub($paymentMethodBase, 'p')
            ->selectRaw("
                p.payment_method,
                COUNT(p.id) as tx_count,
                SUM(p.amount_total) as amount_minor
            ")
            ->groupBy('p.payment_method')
            ->orderByRaw("amount_minor {$sort}")
            ->get();

        $fundingBreakdown = (clone $reservationsBase)
            ->select([])
            ->selectRaw("
                COALESCE(reservations.funding_status, 'unknown') as funding_status,
                COUNT(reservations.id) as booking_count,
                SUM(COALESCE(reservations.total_minor,0)) as total_minor,
                SUM(COALESCE(reservations.wallet_platform_credit_used_minor,0)) as wallet_used_minor,
                SUM(COALESCE(reservations.payable_minor,0)) as payable_minor
            ")
            ->groupBy(DB::raw("COALESCE(reservations.funding_status, 'unknown')"))
            ->orderByRaw('total_minor ' . $sort)
            ->get()
            ->map(function ($row) {
                $row->funding_label = match (strtolower((string) $row->funding_status)) {
                    'wallet_only'   => 'Wallet Only',
                    'mixed'         => 'Mixed Wallet + External',
                    'external_only' => 'External Only',
                    default         => ucwords(str_replace('_', ' ', (string) $row->funding_status)),
                };

                return $row;
            });

        $settlementBreakdown = (clone $reservationsBase)
            ->select([])
            ->selectRaw("
                COALESCE(reservations.settlement_status, 'not_set') as settlement_status,
                COUNT(reservations.id) as row_count,
                SUM(COALESCE(reservations.total_minor,0)) as total_minor
            ")
            ->groupBy(DB::raw("COALESCE(reservations.settlement_status, 'not_set')"))
            ->orderByRaw('row_count ' . $sort)
            ->get();

        $reservationStatusBreakdown = (clone $reservationsBase)
            ->select([])
            ->selectRaw("
                COALESCE(reservations.status, 'unknown') as status,
                COUNT(reservations.id) as row_count,
                SUM(COALESCE(reservations.total_minor,0)) as total_minor
            ")
            ->groupBy(DB::raw("COALESCE(reservations.status, 'unknown')"))
            ->orderByRaw('row_count ' . $sort)
            ->get();

        $refundStatusBreakdown = (clone $refundsBase)
            ->select([])
            ->selectRaw("
                COALESCE(refunds.status, 'unknown') as status,
                COUNT(refunds.id) as row_count,
                SUM(COALESCE(refunds.requested_amount_minor,0)) as requested_minor,
                SUM(COALESCE(refunds.refunded_to_wallet_minor,0)) as refunded_to_wallet_minor,
                SUM(COALESCE(refunds.refunded_to_original_minor,0)) as refunded_to_original_minor,
                SUM(COALESCE(refunds.refunded_to_wallet_minor,0) + COALESCE(refunds.refunded_to_original_minor,0)) as refunded_minor
            ")
            ->groupBy(DB::raw("COALESCE(refunds.status, 'unknown')"))
            ->orderByRaw('row_count ' . $sort)
            ->get();

        $cancelledByBreakdown = (clone $reservationsBase)
            ->where(function ($q) {
                $q->whereIn('reservations.status', ['cancelled', 'canceled', 'no_show', 'no_show_coach', 'no_show_client', 'no_show_both'])
                  ->orWhereNotNull('reservations.cancelled_by');
            })
            ->select([])
            ->selectRaw("
                COALESCE(reservations.cancelled_by, 'system_or_unknown') as cancelled_by,
                COUNT(reservations.id) as row_count,
                SUM(COALESCE(reservations.refund_total_minor,0)) as refund_minor,
                SUM(COALESCE(reservations.platform_earned_minor,0)) as platform_earned_minor,
                SUM(COALESCE(reservations.coach_comp_minor,0)) as coach_comp_minor,
                SUM(COALESCE(reservations.coach_penalty_minor,0)) as coach_penalty_minor
            ")
            ->groupBy(DB::raw("COALESCE(reservations.cancelled_by, 'system_or_unknown')"))
            ->orderByRaw('row_count ' . $sort)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | SERVICES / CATEGORIES
        |--------------------------------------------------------------------------
        */

        $categoryBreakdown = $this->buildCategoryBreakdown($reservationsBase, $sort);

      $topServices = (clone $reservationsBase)
    ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
    ->select([])
    ->selectRaw("
        reservations.service_id,
        COALESCE(services.title, reservations.service_title_snapshot, 'Unknown Service') as service_title,
        COALESCE(NULLIF(service_categories.name,''), 'Uncategorized') as category_name,
        COUNT(reservations.id) as bookings_count,
        SUM(COALESCE(reservations.total_minor,0)) as sales_minor,
        SUM(COALESCE(reservations.subtotal_minor,0)) as service_minor,
        SUM(COALESCE(reservations.platform_earned_minor,0)) as platform_earned_minor
    ")
    ->groupBy('reservations.service_id', 'service_title', 'category_name')
    ->orderByRaw('sales_minor ' . $sort)
    ->limit(50)
    ->get();

        $coachBuiltCategories = $this->buildCoachCategoryBreakdown($reservationsBase, $sort);

        /*
        |--------------------------------------------------------------------------
        | COACH / CLIENT TABLES
        |--------------------------------------------------------------------------
        */

        $topCoaches = (clone $reservationsBase)
            ->leftJoin('users as coach_users', 'coach_users.id', '=', 'reservations.coach_id')
            ->select([])
            ->selectRaw("
                reservations.coach_id,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(coach_users.first_name,''), ' ', COALESCE(coach_users.last_name,''))), ''), 'Unknown Coach') as coach_name,
                COUNT(reservations.id) as bookings_count,
                SUM(COALESCE(reservations.total_minor,0)) as sales_minor,
                SUM(COALESCE(reservations.subtotal_minor,0)) as service_minor,
                SUM(COALESCE(reservations.coach_earned_minor,0)) as coach_earned_minor,
                SUM(COALESCE(reservations.coach_comp_minor,0)) as coach_comp_minor,
                SUM(COALESCE(reservations.coach_penalty_minor,0)) as coach_penalty_minor,
                SUM(COALESCE(reservations.coach_earned_minor,0) + COALESCE(reservations.coach_comp_minor,0) - COALESCE(reservations.coach_penalty_minor,0)) as coach_net_benefit_minor,
                SUM(COALESCE(reservations.platform_earned_minor,0)) as platform_earned_minor,
                MAX(COALESCE(coach_users.coach_rating_avg, 0)) as coach_rating_avg,
                MAX(COALESCE(coach_users.coach_rating_count, 0)) as coach_rating_count
            ")
            ->groupBy('reservations.coach_id', 'coach_name')
            ->orderByRaw(($ratingSort === 'asc' ? 'coach_rating_avg asc, ' : '') . 'coach_net_benefit_minor ' . $sort)
            ->limit(50)
            ->get();

        $topClients = (clone $reservationsBase)
            ->leftJoin('users as client_users', 'client_users.id', '=', 'reservations.client_id')
            ->select([])
            ->selectRaw("
                reservations.client_id,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(client_users.first_name,''), ' ', COALESCE(client_users.last_name,''))), ''), 'Unknown Client') as client_name,
                COUNT(reservations.id) as bookings_count,
                SUM(COALESCE(reservations.total_minor,0)) as spending_minor,
                SUM(COALESCE(reservations.subtotal_minor,0)) as service_minor,
                SUM(COALESCE(reservations.fees_minor,0)) as fee_minor,
                SUM(COALESCE(reservations.refund_total_minor,0)) as refunded_minor,
                SUM(COALESCE(reservations.client_penalty_minor,0)) as client_penalty_minor
            ")
            ->groupBy('reservations.client_id', 'client_name')
            ->orderByRaw('spending_minor ' . $sort)
            ->limit(50)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | WITHDRAWALS
        |--------------------------------------------------------------------------
        */

        $withdrawalsByCoach = (clone $payoutsBase)
            ->leftJoin('coach_profiles as coach_profiles_2', 'coach_profiles_2.id', '=', 'coach_payouts.coach_profile_id')
            ->leftJoin('users as coach_users_2', 'coach_users_2.id', '=', 'coach_profiles_2.user_id')
            ->select([])
            ->selectRaw("
                coach_profiles_2.user_id as coach_user_id,
                COALESCE(NULLIF(TRIM(CONCAT(COALESCE(coach_users_2.first_name,''), ' ', COALESCE(coach_users_2.last_name,''))), ''), 'Unknown Coach') as coach_name,
                COUNT(coach_payouts.id) as payout_count,
                SUM(COALESCE(coach_payouts.amount_minor,0)) as withdrawn_minor,
                SUM(CASE WHEN coach_payouts.status = 'paid' THEN COALESCE(coach_payouts.amount_minor,0) ELSE 0 END) as paid_minor,
                SUM(CASE WHEN coach_payouts.status IN ('processing','payout_pending','transfer_created','pending') THEN COALESCE(coach_payouts.amount_minor,0) ELSE 0 END) as pending_minor,
                SUM(CASE WHEN coach_payouts.status IN ('failed','reversed') THEN COALESCE(coach_payouts.amount_minor,0) ELSE 0 END) as failed_minor
            ")
            ->groupBy('coach_profiles_2.user_id', 'coach_name')
            ->orderByRaw('withdrawn_minor ' . $sort)
            ->limit(50)
            ->get();

        $withdrawalsSummary = (clone $payoutsBase)
            ->select([])
            ->selectRaw("
                COUNT(coach_payouts.id) as payout_count,
                SUM(COALESCE(coach_payouts.amount_minor,0)) as total_withdrawn_minor,
                SUM(CASE WHEN coach_payouts.status = 'paid' THEN COALESCE(coach_payouts.amount_minor,0) ELSE 0 END) as paid_withdrawn_minor,
                SUM(CASE WHEN coach_payouts.status IN ('processing','payout_pending','transfer_created','pending') THEN COALESCE(coach_payouts.amount_minor,0) ELSE 0 END) as pending_withdrawn_minor,
                SUM(CASE WHEN coach_payouts.status IN ('failed','reversed') THEN COALESCE(coach_payouts.amount_minor,0) ELSE 0 END) as failed_withdrawn_minor
            ")
            ->first();

        $withdrawalCount       = (int) ($withdrawalsSummary->payout_count ?? 0);
        $totalWithdrawnMinor   = (int) ($withdrawalsSummary->total_withdrawn_minor ?? 0);
        $paidWithdrawnMinor    = (int) ($withdrawalsSummary->paid_withdrawn_minor ?? 0);
        $pendingWithdrawnMinor = (int) ($withdrawalsSummary->pending_withdrawn_minor ?? 0);
        $failedWithdrawnMinor  = (int) ($withdrawalsSummary->failed_withdrawn_minor ?? 0);

        /*
        |--------------------------------------------------------------------------
        | WALLET / LEDGER
        |--------------------------------------------------------------------------
        */

        $coachWalletCreditsMinor = (int) (clone $walletBase)
            ->where('wallet_transactions.balance_type', WalletService::BAL_WITHDRAW)
            ->where('wallet_transactions.type', 'credit')
            ->sum(DB::raw('COALESCE(wallet_transactions.amount_minor,0)'));

        $coachWalletDebitsMinor = (int) (clone $walletBase)
            ->where('wallet_transactions.balance_type', WalletService::BAL_WITHDRAW)
            ->where('wallet_transactions.type', 'debit')
            ->sum(DB::raw('COALESCE(wallet_transactions.amount_minor,0)'));

        $coachWithdrawableNetMovementMinor = $coachWalletCreditsMinor - $coachWalletDebitsMinor;

        /*
        |--------------------------------------------------------------------------
        | USER SIGNUPS
        |--------------------------------------------------------------------------
        */

        $totalUsersCreatedCount  = (int) (clone $usersBase)->count();
        $clientUsersCreatedCount = (int) (clone $usersBase)->where('role', 'client')->count();
        $coachFlagUsersCreatedCount = (int) (clone $usersBase)->where('is_coach', 1)->count();
        $verifiedUsersCount = (int) (clone $usersBase)->whereNotNull('email_verified_at')->count();

        /*
        |--------------------------------------------------------------------------
        | DISPUTE RECORDS
        |--------------------------------------------------------------------------
        */

     $disputeRows = $this->buildDisputeRows($search, $dateFrom, $dateTo);
        /*
        |--------------------------------------------------------------------------
        | TIMELINES / CHARTS
        |--------------------------------------------------------------------------
        */

     $paymentBucketExpr     = $this->bucketExpression($bucketMode, 'COALESCE(payments.succeeded_at, payments.created_at)', $reportTimezone);
$refundBucketExpr      = $this->bucketExpression($bucketMode, 'COALESCE(refunds.processed_at, refunds.requested_at, refunds.created_at)', $reportTimezone);
$reservationBucketExpr = $this->bucketExpression($bucketMode, 'COALESCE(reservations.completed_at, reservations.cancelled_at, reservations.created_at)', $reportTimezone);
$payoutBucketExpr      = $this->bucketExpression($bucketMode, 'COALESCE(coach_payouts.paid_at, coach_payouts.created_at)', $reportTimezone);
$analyticsBucketExpr   = $this->bucketExpression($bucketMode, 'analytics_events.created_at', $reportTimezone);
$reviewsBucketExpr     = $this->bucketExpression($bucketMode, 'reservation_reviews.created_at', $reportTimezone);

        $paymentTimelineRows = (clone $successfulPayments)
            ->select([])
            ->selectRaw("$paymentBucketExpr as bucket")
            ->selectRaw("COUNT(payments.id) as tx_count")
            ->selectRaw("SUM(COALESCE(payments.amount_total,0)) as inflow_minor")
            ->selectRaw("SUM(COALESCE(payments.service_subtotal_minor,0)) as service_minor")
            ->selectRaw("SUM(COALESCE(payments.client_fee_minor,0)) as fee_minor")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $refundTimelineRows = (clone $refundsBase)
            ->select([])
            ->selectRaw("$refundBucketExpr as bucket")
            ->selectRaw("COUNT(refunds.id) as refund_count")
            ->selectRaw("SUM(COALESCE(refunds.refunded_to_wallet_minor,0) + COALESCE(refunds.refunded_to_original_minor,0)) as refund_minor")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $refundCashTimelineRows = (clone $refundsBase)
            ->select([])
            ->selectRaw("$refundBucketExpr as bucket")
            ->selectRaw("SUM(COALESCE(refunds.refunded_to_original_minor,0)) as refund_cash_minor")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $reservationTimelineRows = (clone $reservationsBase)
            ->select([])
            ->selectRaw("$reservationBucketExpr as bucket")
            ->selectRaw("COUNT(reservations.id) as reservations_count")
            ->selectRaw("SUM(COALESCE(reservations.platform_earned_minor,0)) as platform_earned_minor")
            ->selectRaw("SUM(COALESCE(reservations.coach_earned_minor,0)) as coach_earned_minor")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $payoutTimelineRows = (clone $payoutsBase)
            ->select([])
            ->selectRaw("$payoutBucketExpr as bucket")
            ->selectRaw("COUNT(coach_payouts.id) as payout_count")
            ->selectRaw("SUM(COALESCE(coach_payouts.amount_minor,0)) as amount_minor")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $siteVisitTimelineRows = (clone $analyticsEventsBase)
            ->where('type', 'site_visit')
            ->selectRaw("$analyticsBucketExpr as bucket")
            ->selectRaw("COUNT(analytics_events.id) as visit_count")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $signupCreatedTimelineRows = (clone $analyticsEventsBase)
            ->where('type', 'signup_created')
            ->selectRaw("$analyticsBucketExpr as bucket")
            ->selectRaw("COUNT(analytics_events.id) as signup_count")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $signupVerifiedTimelineRows = (clone $analyticsEventsBase)
            ->where('type', 'signup_verified')
            ->selectRaw("$analyticsBucketExpr as bucket")
            ->selectRaw("COUNT(analytics_events.id) as verified_count")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $bookingPageVisitTimelineRows = (clone $analyticsEventsBase)
            ->where('type', 'booking_page_visit')
            ->selectRaw("$analyticsBucketExpr as bucket")
            ->selectRaw("COUNT(analytics_events.id) as booking_page_visit_count")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $coachApplicationTimelineRows = (clone $analyticsEventsBase)
            ->where('type', 'coach_application_submitted')
            ->selectRaw("$analyticsBucketExpr as bucket")
            ->selectRaw("COUNT(analytics_events.id) as submitted_count")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $reviewTimelineRows = (clone $reviewsBase)
            ->where('reservation_reviews.reviewee_role', 'coach')
            ->select([])
            ->selectRaw("$reviewsBucketExpr as bucket")
            ->selectRaw("COUNT(reservation_reviews.id) as review_count")
            ->selectRaw("AVG(reservation_reviews.stars) as avg_rating")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $allBuckets = collect()
            ->merge($paymentTimelineRows->pluck('bucket'))
            ->merge($refundTimelineRows->pluck('bucket'))
            ->merge($refundCashTimelineRows->pluck('bucket'))
            ->merge($reservationTimelineRows->pluck('bucket'))
            ->merge($payoutTimelineRows->pluck('bucket'))
            ->merge($siteVisitTimelineRows->pluck('bucket'))
            ->merge($signupCreatedTimelineRows->pluck('bucket'))
            ->merge($signupVerifiedTimelineRows->pluck('bucket'))
            ->merge($bookingPageVisitTimelineRows->pluck('bucket'))
            ->merge($coachApplicationTimelineRows->pluck('bucket'))
            ->merge($reviewTimelineRows->pluck('bucket'))
            ->unique()
            ->sort()
            ->values();

        $paymentsMap         = $paymentTimelineRows->keyBy('bucket');
        $refundsMap          = $refundTimelineRows->keyBy('bucket');
        $refundCashMap       = $refundCashTimelineRows->keyBy('bucket');
        $reservationsMap     = $reservationTimelineRows->keyBy('bucket');
        $payoutsMap          = $payoutTimelineRows->keyBy('bucket');
        $siteVisitsMap       = $siteVisitTimelineRows->keyBy('bucket');
        $signupCreatedMap    = $signupCreatedTimelineRows->keyBy('bucket');
        $signupVerifiedMap   = $signupVerifiedTimelineRows->keyBy('bucket');
        $bookingPageVisitMap = $bookingPageVisitTimelineRows->keyBy('bucket');
        $coachAppMap         = $coachApplicationTimelineRows->keyBy('bucket');
        $reviewMap           = $reviewTimelineRows->keyBy('bucket');

        $lineLabels = $allBuckets->values()->all();

        $lineInflow      = $allBuckets->map(fn ($bucket) => (int) ($paymentsMap[$bucket]->inflow_minor ?? 0))->values()->all();
        $lineRefunds     = $allBuckets->map(fn ($bucket) => (int) ($refundsMap[$bucket]->refund_minor ?? 0))->values()->all();
        $lineRefundCash  = $allBuckets->map(fn ($bucket) => (int) ($refundCashMap[$bucket]->refund_cash_minor ?? 0))->values()->all();
        $linePlatform    = $allBuckets->map(fn ($bucket) => (int) ($reservationsMap[$bucket]->platform_earned_minor ?? 0))->values()->all();
        $lineCoach       = $allBuckets->map(fn ($bucket) => (int) ($reservationsMap[$bucket]->coach_earned_minor ?? 0))->values()->all();
        $lineWithdrawals = $allBuckets->map(fn ($bucket) => (int) ($payoutsMap[$bucket]->amount_minor ?? 0))->values()->all();

        $lineVisits             = $allBuckets->map(fn ($bucket) => (int) ($siteVisitsMap[$bucket]->visit_count ?? 0))->values()->all();
        $lineSignups            = $allBuckets->map(fn ($bucket) => (int) ($signupCreatedMap[$bucket]->signup_count ?? 0))->values()->all();
        $lineSignupVerified     = $allBuckets->map(fn ($bucket) => (int) ($signupVerifiedMap[$bucket]->verified_count ?? 0))->values()->all();
        $lineBookingPageVisits  = $allBuckets->map(fn ($bucket) => (int) ($bookingPageVisitMap[$bucket]->booking_page_visit_count ?? 0))->values()->all();
        $lineCoachApplications  = $allBuckets->map(fn ($bucket) => (int) ($coachAppMap[$bucket]->submitted_count ?? 0))->values()->all();
        $lineReviewCounts       = $allBuckets->map(fn ($bucket) => (int) ($reviewMap[$bucket]->review_count ?? 0))->values()->all();
        $lineAverageCoachRating = $allBuckets->map(fn ($bucket) => round((float) ($reviewMap[$bucket]->avg_rating ?? 0), 2))->values()->all();

        $barProviderLabels = $providerBreakdown->pluck('provider')
            ->map(fn ($v) => ucwords(str_replace('_', ' ', (string) $v)))
            ->values()
            ->all();

        $barProviderValues = $providerBreakdown->pluck('amount_minor')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $barCategoryLabels = $categoryBreakdown->pluck('category_name')
            ->map(fn ($v) => str_replace('_', ' ', (string) $v))
            ->values()
            ->all();

        $barCategorySales = $categoryBreakdown->pluck('sales_minor')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $barCategoryBookings = $categoryBreakdown->pluck('bookings_count')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $pieBusinessMoneyLabels = ['External Payments', 'Wallet Payments', 'Refund Cash Outflow', 'Net Business Cash'];
        $pieBusinessMoneyValues = [
            (int) $externalInflowMinor,
            (int) $walletInflowMinor,
            (int) $refundOutflowMinor,
            (int) max(0, $netBusinessCashMinor),
        ];

        $pieFundingLabels = $fundingBreakdown->pluck('funding_label')->values()->all();
        $pieFundingValues = $fundingBreakdown->pluck('total_minor')->map(fn ($v) => (int) $v)->values()->all();

        $pieSettlementLabels = $settlementBreakdown->pluck('settlement_status')
            ->map(fn ($v) => ucwords(str_replace('_', ' ', (string) $v)))
            ->values()
            ->all();

        $pieSettlementValues = $settlementBreakdown->pluck('total_minor')
            ->map(fn ($v) => (int) $v)
            ->values()
            ->all();

        $pieRefundLabels = ['Refunded To Wallet', 'Refunded To Original Method'];
        $pieRefundValues = [
            (int) $refundWalletMinor,
            (int) $refundOriginalMinor,
        ];

        /*
        |--------------------------------------------------------------------------
        | DETAIL TABLES WITH CLICK TARGETS
        |--------------------------------------------------------------------------
        */

        $paymentRows = (clone $paymentsBase)
            ->leftJoin('reservations as r1', 'r1.id', '=', 'payments.reservation_id')
            ->leftJoin('users as clients_1', 'clients_1.id', '=', 'r1.client_id')
            ->leftJoin('users as coaches_1', 'coaches_1.id', '=', 'r1.coach_id')
            ->leftJoin('services as services_1', 'services_1.id', '=', 'r1.service_id')
            ->select([
                'payments.id',
                'payments.reservation_id',
                'payments.provider',
                'payments.method',
                'payments.status',
                'payments.refund_status',
                'payments.currency',
                'payments.amount_total',
                'payments.service_subtotal_minor',
                'payments.client_fee_minor',
                'payments.coach_fee_minor',
                'payments.coach_earnings',
                'payments.platform_fee',
                'payments.refunded_minor',
                'payments.succeeded_at',
                'payments.created_at',
                DB::raw("COALESCE(services_1.title, r1.service_title_snapshot, 'Unknown Service') as service_title"),
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(clients_1.first_name,''), ' ', COALESCE(clients_1.last_name,''))), ''), 'Unknown Client') as client_name"),
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(coaches_1.first_name,''), ' ', COALESCE(coaches_1.last_name,''))), ''), 'Unknown Coach') as coach_name"),
                DB::raw("'" . route('admin.bookings.index') . "' as bookings_index_link"),
            ])
            ->orderByDesc(DB::raw('COALESCE(payments.succeeded_at, payments.created_at)'))
            ->paginate(12, ['*'], 'payments_page')
            ->withQueryString();

        $refundRows = (clone $refundsBase)
            ->leftJoin('reservations as r2', 'r2.id', '=', 'refunds.reservation_id')
            ->leftJoin('users as clients_2', 'clients_2.id', '=', 'r2.client_id')
            ->leftJoin('users as coaches_2', 'coaches_2.id', '=', 'r2.coach_id')
            ->leftJoin('services as services_2', 'services_2.id', '=', 'r2.service_id')
            ->select([
                'refunds.id',
                'refunds.reservation_id',
                'refunds.provider',
                'refunds.method',
                'refunds.status',
                'refunds.wallet_status',
                'refunds.external_status',
                'refunds.currency',
                'refunds.requested_amount_minor',
                'refunds.actual_amount_minor',
                'refunds.wallet_amount_minor',
                'refunds.external_amount_minor',
                'refunds.refunded_to_wallet_minor',
                'refunds.refunded_to_original_minor',
                'refunds.requested_at',
                'refunds.processed_at',
                DB::raw("COALESCE(services_2.title, r2.service_title_snapshot, 'Unknown Service') as service_title"),
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(clients_2.first_name,''), ' ', COALESCE(clients_2.last_name,''))), ''), 'Unknown Client') as client_name"),
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(coaches_2.first_name,''), ' ', COALESCE(coaches_2.last_name,''))), ''), 'Unknown Coach') as coach_name"),
            ])
            ->orderByDesc(DB::raw('COALESCE(refunds.processed_at, refunds.requested_at, refunds.created_at)'))
            ->paginate(12, ['*'], 'refunds_page')
            ->withQueryString();

        $reservationRows = (clone $reservationsBase)
            ->leftJoin('users as clients_3', 'clients_3.id', '=', 'reservations.client_id')
            ->leftJoin('users as coaches_3', 'coaches_3.id', '=', 'reservations.coach_id')
            ->leftJoin('services as services_3', 'services_3.id', '=', 'reservations.service_id')
            ->select([
                'reservations.id',
                'reservations.status',
                'reservations.payment_status',
                'reservations.settlement_status',
                'reservations.refund_status',
                'reservations.cancelled_by',
                'reservations.funding_status',
                'reservations.currency',
                'reservations.created_at',
                'reservations.completed_at',
                'reservations.cancelled_at',
                'reservations.subtotal_minor',
                'reservations.fees_minor',
                'reservations.total_minor',
                'reservations.wallet_platform_credit_used_minor',
                'reservations.payable_minor',
                'reservations.platform_earned_minor',
                'reservations.platform_fee_refunded_minor',
                'reservations.coach_earned_minor',
                'reservations.coach_comp_minor',
                'reservations.coach_penalty_minor',
                'reservations.client_penalty_minor',
                'reservations.refund_total_minor',
                DB::raw("COALESCE(services_3.title, reservations.service_title_snapshot, 'Unknown Service') as service_title"),
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(clients_3.first_name,''), ' ', COALESCE(clients_3.last_name,''))), ''), 'Unknown Client') as client_name"),
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(coaches_3.first_name,''), ' ', COALESCE(coaches_3.last_name,''))), ''), 'Unknown Coach') as coach_name"),
            ])
            ->orderByDesc(DB::raw('COALESCE(reservations.completed_at, reservations.cancelled_at, reservations.updated_at, reservations.created_at)'))
            ->paginate(12, ['*'], 'reservations_page')
            ->withQueryString();

        $payoutRows = (clone $payoutsBase)
            ->leftJoin('coach_profiles as cp3', 'cp3.id', '=', 'coach_payouts.coach_profile_id')
            ->leftJoin('users as coach_users_3', 'coach_users_3.id', '=', 'cp3.user_id')
            ->select([
                'coach_payouts.id',
                'coach_payouts.provider',
                'coach_payouts.status',
                'coach_payouts.currency',
                'coach_payouts.amount_minor',
                'coach_payouts.reservation_count',
                'coach_payouts.provider_transfer_id',
                'coach_payouts.provider_payout_id',
                'coach_payouts.failure_reason',
                'coach_payouts.created_at',
                'coach_payouts.paid_at',
                'coach_payouts.failed_at',
                DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(coach_users_3.first_name,''), ' ', COALESCE(coach_users_3.last_name,''))), ''), 'Unknown Coach') as coach_name"),
            ])
            ->orderByDesc(DB::raw('COALESCE(coach_payouts.paid_at, coach_payouts.created_at)'))
            ->paginate(12, ['*'], 'payouts_page')
            ->withQueryString();

       $reviewRows = (clone $reviewsBase)
    ->leftJoin('users as reviewees_2', 'reviewees_2.id', '=', 'reservation_reviews.reviewee_id')
    ->leftJoin('reservations as rr_res', 'rr_res.id', '=', 'reservation_reviews.reservation_id')
    ->select([
        'reservation_reviews.id',
        'reservation_reviews.reservation_id',
        'reservation_reviews.reviewee_id',
        'reservation_reviews.reviewee_role',
        'reservation_reviews.stars',
        'reservation_reviews.description',
        'reservation_reviews.created_at',
        DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(reviewees_2.first_name,''), ' ', COALESCE(reviewees_2.last_name,''))), ''), 'Unknown User') as reviewee_name"),
    ])
            ->orderByDesc('reservation_reviews.created_at')
            ->paginate(12, ['*'], 'reviews_page')
            ->withQueryString();

        /*
        |--------------------------------------------------------------------------
        | DROPDOWNS / FILTER OPTIONS
        |--------------------------------------------------------------------------
        */

        $serviceOptions = Reservation::query()
            ->leftJoin('services', 'services.id', '=', 'reservations.service_id')
            ->selectRaw("DISTINCT COALESCE(services.title, reservations.service_title_snapshot, 'Unknown Service') as label")
            ->orderBy('label')
            ->pluck('label')
            ->filter()
            ->values();

        $categoryOptions = $categoryBreakdown->pluck('category_name')->filter()->values();
        $providerOptions = Payment::query()->select('provider')->distinct()->pluck('provider')->filter()->values();
        $fundingOptions = Reservation::query()->select('funding_status')->distinct()->pluck('funding_status')->filter()->values();
        $settlementOptions = Reservation::query()->select('settlement_status')->distinct()->pluck('settlement_status')->filter()->values();
        $reservationStatusOptions = Reservation::query()->select('status')->distinct()->pluck('status')->filter()->values();
        $payoutStatusOptions = CoachPayout::query()->select('status')->distinct()->pluck('status')->filter()->values();
        $refundStatusOptions = Refund::query()->select('status')->distinct()->pluck('status')->filter()->values();

        return view('superadmin.analytics.index', [
            'range' => $range,
            'periodLabel' => $periodLabel,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'fmt' => $fmt,

            'search' => $search,
            'sort' => $sort,
            'ratingSort' => $ratingSort,
            'serviceFilter' => $serviceFilter,
            'categoryFilter' => $categoryFilter,
            'coachFilter' => $coachFilter,
            'clientFilter' => $clientFilter,
            'paymentProvider' => $paymentProvider,
            'fundingFilter' => $fundingFilter,
            'settlementFilter' => $settlementFilter,
            'reservationStatus' => $reservationStatus,
            'payoutStatus' => $payoutStatus,
            'refundStatus' => $refundStatus,
            'reviewRole' => $reviewRole,
                'reportTimezone' => $reportTimezone,
            'serviceOptions' => $serviceOptions,
            'categoryOptions' => $categoryOptions,
            'providerOptions' => $providerOptions,
            'fundingOptions' => $fundingOptions,
            'settlementOptions' => $settlementOptions,
            'reservationStatusOptions' => $reservationStatusOptions,
            'payoutStatusOptions' => $payoutStatusOptions,
            'refundStatusOptions' => $refundStatusOptions,

            'paymentCount' => $paymentCount,
            'grossInflowMinor' => $grossInflowMinor,
            'externalInflowMinor' => $externalInflowMinor,
            'walletInflowMinor' => $walletInflowMinor,
            'netCashMinor' => $netBusinessCashMinor,
            'businessRetentionRate' => $businessRetentionRate,
            'businessMoneyKpis' => $businessMoneyKpis,
            'incomingOutgoing' => $incomingOutgoing,

            'bucketMode' => $bucketMode,
            'serviceSalesMinor' => $serviceSalesMinor,
            'clientFeeCollectedMinor' => $clientFeeCollectedMinor,
            'coachCommissionCollectedMinor' => $coachCommissionCollectedMinor,
            'platformEarnedMinor' => $platformEarnedMinor,
            'platformFeeRefundedMinor' => $platformFeeRefundedMinor,
            'platformNetEarnedMinor' => $platformNetEarnedMinor,

            'reservationsCount' => $reservationsCount,
            'paidReservationsCount' => $paidReservationsCount,
            'completedCount' => $completedCount,
            'cancelledCount' => $cancelledCount,
            'noShowCount' => $noShowCount,
            'reservationSubtotalMinor' => $reservationSubtotalMinor,
            'reservationFeesMinor' => $reservationFeesMinor,
            'reservationTotalMinor' => $reservationTotalMinor,
            'reservationWalletUsedMinor' => $reservationWalletUsedMinor,
            'reservationPayableMinor' => $reservationPayableMinor,

            'refundSucceededMinor' => $refundSucceededMinor,
            'refundPartialMinor' => $refundPartialMinor,
            'refundProcessingMinor' => $refundProcessingMinor,
            'refundWalletMinor' => $refundWalletMinor,
            'refundOriginalMinor' => $refundOriginalMinor,
            'refundExternalMinor' => $refundOriginalMinor,
            'refundOverallMinor' => $totalRefundedMinor,
            'totalRefundedMinor' => $totalRefundedMinor,
            'refundSucceededCount' => $refundSucceededCount,
            'refundPartialCount' => $refundPartialCount,
            'refundFailedCount' => $refundFailedCount,
            'refundProcessingCount' => $refundProcessingCount,
            'refundOutflowMinor' => $refundOutflowMinor,

            'coachReleasedMinor' => $coachReleasedMinor,
            'coachCompMinor' => $coachCompMinor,
            'coachPenaltyMinor' => $coachPenaltyMinor,
            'coachNetBenefitMinor' => $coachNetBenefitMinor,
            'clientPenaltyMinor' => $clientPenaltyMinor,

            'siteVisitCount' => $siteVisitCount,
            'uniqueVisitorCount' => $uniqueVisitorCount,
            'bookingPageVisitCount' => $bookingPageVisitCount,
            'walletCheckoutStartedCount' => $walletCheckoutStartedCount,
            'stripeCheckoutStartedCount' => $stripeCheckoutStartedCount,
            'paypalCheckoutStartedCount' => $paypalCheckoutStartedCount,
            'checkoutStartedCount' => $checkoutStartedCount,
            'bookingPaidEventCount' => $bookingPaidEventCount,
            'trafficAndConversion' => $trafficAndConversion,

            'signupCreatedCount' => $signupCreatedCount,
            'signupVerifiedCount' => $signupVerifiedCount,
            'clientSignupCreatedCount' => $clientSignupCreatedCount,
            'coachSignupCreatedCount' => $coachSignupCreatedCount,
            'clientSignupVerifiedCount' => $clientSignupVerifiedCount,
            'coachSignupVerifiedCount' => $coachSignupVerifiedCount,
            'coachApplicationSubmittedCount' => $coachApplicationSubmittedCount,
            'otpResentCount' => $otpResentCount,
            'otpFailedCount' => $otpFailedCount,
            'visitToSignupRate' => $trafficAndConversion['visit_to_signup_rate'],
            'signupToVerificationRate' => $trafficAndConversion['signup_to_verification_rate'],
            'visitToBookingPageRate' => $trafficAndConversion['visit_to_booking_page_rate'],
            'checkoutToPaidRate' => $trafficAndConversion['checkout_to_paid_rate'],
            'visitToPaidBookingRate' => $trafficAndConversion['visit_to_paid_booking_rate'],

            'reviewCount' => $reviewCount,
            'coachReviewCount' => $coachReviewCount,
            'averageCoachRating' => $averageCoachRating,
            'ratingBreakdown' => $ratingBreakdown,
            'topRatedCoaches' => $topRatedCoaches,

            'withdrawalCount' => $withdrawalCount,
            'totalWithdrawnMinor' => $totalWithdrawnMinor,
            'paidWithdrawnMinor' => $paidWithdrawnMinor,
            'pendingWithdrawnMinor' => $pendingWithdrawnMinor,
            'failedWithdrawnMinor' => $failedWithdrawnMinor,
            'coachWalletCreditsMinor' => $coachWalletCreditsMinor,
            'coachWalletDebitsMinor' => $coachWalletDebitsMinor,
            'coachWithdrawableNetMovementMinor' => $coachWithdrawableNetMovementMinor,

            'totalUsersCreatedCount' => $totalUsersCreatedCount,
            'clientUsersCreatedCount' => $clientUsersCreatedCount,
            'coachFlagUsersCreatedCount' => $coachFlagUsersCreatedCount,
            'verifiedUsersCount' => $verifiedUsersCount,

            'providerBreakdown' => $providerBreakdown,
            'paymentMethodBreakdown' => $paymentMethodBreakdown,
            'fundingBreakdown' => $fundingBreakdown,
            'settlementBreakdown' => $settlementBreakdown,
            'reservationStatusBreakdown' => $reservationStatusBreakdown,
            'refundStatusBreakdown' => $refundStatusBreakdown,
            'cancelledByBreakdown' => $cancelledByBreakdown,
            'categoryBreakdown' => $categoryBreakdown,
            'coachBuiltCategories' => $coachBuiltCategories,

            'topCoaches' => $topCoaches,
            'topClients' => $topClients,
            'worstCoaches' => $worstCoaches,
            'withdrawalsByCoach' => $withdrawalsByCoach,
            'topServices' => $topServices,

            'disputeRows' => $disputeRows,

            'lineLabels' => $lineLabels,
            'lineInflow' => $lineInflow,
            'lineRefunds' => $lineRefunds,
            'lineRefundCash' => $lineRefundCash,
            'linePlatform' => $linePlatform,
            'lineCoach' => $lineCoach,
            'lineWithdrawals' => $lineWithdrawals,
            'lineVisits' => $lineVisits,
            'lineSignups' => $lineSignups,
            'lineSignupVerified' => $lineSignupVerified,
            'lineBookingPageVisits' => $lineBookingPageVisits,
            'lineCoachApplications' => $lineCoachApplications,
            'lineReviewCounts' => $lineReviewCounts,
            'lineAverageCoachRating' => $lineAverageCoachRating,

            'barProviderLabels' => $barProviderLabels,
            'barProviderValues' => $barProviderValues,
            'barCategoryLabels' => $barCategoryLabels,
            'barCategorySales' => $barCategorySales,
            'barCategoryBookings' => $barCategoryBookings,

            'pieBusinessMoneyLabels' => $pieBusinessMoneyLabels,
            'pieBusinessMoneyValues' => $pieBusinessMoneyValues,
            'pieFundingLabels' => $pieFundingLabels,
            'pieFundingValues' => $pieFundingValues,
            'pieSettlementLabels' => $pieSettlementLabels,
            'pieSettlementValues' => $pieSettlementValues,
            'pieRefundLabels' => $pieRefundLabels,
            'pieRefundValues' => $pieRefundValues,

            'paymentRows' => $paymentRows,
            'refundRows' => $refundRows,
            'reservationRows' => $reservationRows,
            'payoutRows' => $payoutRows,
            'reviewRows' => $reviewRows,
        ]);
    }

  private function resolveRange(Request $request, string $timezone = 'UTC'): array
{
   $tz = $timezone ?: config('app.timezone', 'UTC');
    $now = CarbonImmutable::now($tz);

    $range     = strtolower((string) $request->query('range', 'lifetime'));
    $from      = $request->query('from');
    $to        = $request->query('to');
    $year      = (int) $request->query('year', $now->year);
    $month     = (int) $request->query('month', $now->month);
    $day       = $request->query('day');
$weekDay   = $request->query('week_day');
    $timeFrom  = trim((string) $request->query('time_from', ''));
    $timeTo    = trim((string) $request->query('time_to', ''));

    if (in_array($range, ['all', 'lifetime'], true)) {
        $range = 'lifetime';
    }

    if ($range === 'custom') {
    $range = 'custom';
}

    $dateFrom = null;
    $dateTo = null;
    $periodLabel = 'All Time';
    $bucketMode = 'monthly';

    switch ($range) {
        case 'daily':
            $base = $day ? CarbonImmutable::parse($day, $tz) : $now;
            $dateFrom = $base->startOfDay();
            $dateTo   = $base->endOfDay();
            $periodLabel = $base->format('Y-m-d');
            $bucketMode = 'hourly';
            break;

      case 'weekly':
    $weekBase = $weekDay ? CarbonImmutable::parse($weekDay, $tz) : $now;
    $dateFrom = $weekBase->startOfWeek()->startOfDay();
    $dateTo   = $weekBase->endOfWeek()->endOfDay();
    $periodLabel = $dateFrom->format('Y-m-d') . ' → ' . $dateTo->format('Y-m-d');
    $bucketMode = 'daily';
    break;

        case 'monthly':
            $base = $now->setYear($year)->setMonth($month);
            $dateFrom = $base->startOfMonth()->startOfDay();
            $dateTo   = $base->endOfMonth()->endOfDay();
            $periodLabel = $base->format('F Y');
            $bucketMode = 'daily';
            break;

        case 'yearly':
            $base = $now->setYear($year);
            $dateFrom = $base->startOfYear()->startOfDay();
            $dateTo   = $base->endOfYear()->endOfDay();
            $periodLabel = (string) $year;
            $bucketMode = 'monthly';
            break;

        case 'custom':
            $dateFrom = $from ? CarbonImmutable::parse($from, $tz)->startOfDay() : null;
            $dateTo   = $to ? CarbonImmutable::parse($to, $tz)->endOfDay() : null;
            $periodLabel = trim(
                ($dateFrom ? $dateFrom->format('Y-m-d') : '…') . ' → ' .
                ($dateTo ? $dateTo->format('Y-m-d') : '…')
            );
            $bucketMode = 'daily';
            break;

        case 'lifetime':
        default:
            $dateFrom = null;
            $dateTo   = null;
            $periodLabel = 'All Time';
            $bucketMode = 'monthly';
            break;
    }

    if ($dateFrom && $timeFrom !== '') {
        [$fromHour, $fromMinute] = $this->parseTimeInput($timeFrom);
        $dateFrom = $dateFrom->setTime($fromHour, $fromMinute, 0);
    }

    if ($dateTo && $timeTo !== '') {
        [$toHour, $toMinute] = $this->parseTimeInput($timeTo);
        $dateTo = $dateTo->setTime($toHour, $toMinute, 59);
    }

    if ($dateFrom && $dateTo && $dateFrom->gt($dateTo)) {
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    if ($range === 'daily') {
        $periodLabel = $dateFrom && $dateTo
            ? $dateFrom->format('Y-m-d h:i A') . ' → ' . $dateTo->format('h:i A')
            : $periodLabel;
    } elseif ($range === 'custom' && ($timeFrom !== '' || $timeTo !== '')) {
        $periodLabel = trim(
            ($dateFrom ? $dateFrom->format('Y-m-d h:i A') : '…') . ' → ' .
            ($dateTo ? $dateTo->format('Y-m-d h:i A') : '…')
        );
    }

    if (
        $dateFrom &&
        $dateTo &&
        $dateFrom->toDateString() === $dateTo->toDateString()
    ) {
        $bucketMode = 'hourly';
    }

    return [
    $dateFrom?->utc(),
    $dateTo?->utc(),
    $range,
    $periodLabel,
    $bucketMode
];
}

 private function bucketExpression(string $bucketMode, string $columnExpr, string $timezone = 'UTC'): string
{
    return match ($bucketMode) {
        'hourly' => "DATE_FORMAT($columnExpr, '%Y-%m-%d %H:00')",
        'daily'  => "DATE_FORMAT($columnExpr, '%Y-%m-%d')",
        default  => "DATE_FORMAT($columnExpr, '%Y-%m')",
    };
}

private function parseTimeInput(string $time): array
{
    $time = trim($time);

    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        return [0, 0];
    }

    [$hour, $minute] = array_map('intval', explode(':', $time));

    $hour = max(0, min(23, $hour));
    $minute = max(0, min(59, $minute));

    return [$hour, $minute];
}
private function applyCategoryFilter($query, string $categoryFilter): void
{
    $query->whereExists(function ($sub) use ($categoryFilter) {
        $sub->select(DB::raw(1))
            ->from('service_categories')
            ->whereColumn('service_categories.id', 'services.category_id')
            ->where('service_categories.name', 'like', "%{$categoryFilter}%");
    });
}
  private function buildCategoryBreakdown($reservationsBase, string $sort = 'desc')
{
    return (clone $reservationsBase)
       ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
        ->select([])
        ->selectRaw("
            COALESCE(NULLIF(service_categories.name,''), 'Uncategorized') as category_name,
            COUNT(reservations.id) as bookings_count,
            SUM(COALESCE(reservations.total_minor,0)) as sales_minor,
            SUM(COALESCE(reservations.platform_earned_minor,0)) as platform_earned_minor,
            SUM(COALESCE(reservations.coach_earned_minor,0)) as coach_earned_minor
        ")
        ->groupBy('category_name')
        ->orderByRaw('sales_minor ' . $sort)
        ->get();
}

   private function buildCoachCategoryBreakdown($reservationsBase, string $sort = 'desc')
{
    return (clone $reservationsBase)
        ->leftJoin('service_categories', 'service_categories.id', '=', 'services.category_id')
        ->select([])
        ->selectRaw("
            reservations.coach_id,
            COALESCE(NULLIF(TRIM(CONCAT(COALESCE(coaches.first_name,''), ' ', COALESCE(coaches.last_name,''))), ''), 'Unknown Coach') as coach_name,
            COALESCE(NULLIF(service_categories.name,''), 'Uncategorized') as category_name,
            COUNT(DISTINCT services.id) as services_count,
            COUNT(reservations.id) as bookings_count,
            SUM(COALESCE(reservations.total_minor,0)) as sales_minor
        ")
        ->groupBy('reservations.coach_id', 'coach_name', 'category_name')
        ->orderByRaw('sales_minor ' . $sort)
        ->limit(100)
        ->get();
}

private function buildDisputeRows(string $search = '', $dateFrom = null, $dateTo = null)
{
    $disputeDateExpr = 'COALESCE(disputes.resolved_at, disputes.decided_at, disputes.updated_at, disputes.created_at)';

    $query = Dispute::query()
        ->leftJoin('reservations', 'reservations.id', '=', 'disputes.reservation_id')
        ->leftJoin('services', 'services.id', '=', 'reservations.service_id')
        ->leftJoin('users as dispute_clients', 'dispute_clients.id', '=', 'reservations.client_id')
        ->leftJoin('users as dispute_coaches', 'dispute_coaches.id', '=', 'reservations.coach_id')
        ->when($dateFrom, fn ($q) => $q->whereRaw("$disputeDateExpr >= ?", [$dateFrom]))
        ->when($dateTo, fn ($q) => $q->whereRaw("$disputeDateExpr <= ?", [$dateTo]));

    if ($search !== '') {
        $query->where(function ($w) use ($search) {
            if (ctype_digit($search)) {
                $w->orWhere('disputes.id', (int) $search)
                  ->orWhere('disputes.reservation_id', (int) $search)
                  ->orWhere('reservations.id', (int) $search);
            }

            $w->orWhere('disputes.status', 'like', "%{$search}%")
              ->orWhere('disputes.opened_by_role', 'like', "%{$search}%");

            if (Schema::hasColumn('disputes', 'reason')) {
                $w->orWhere('disputes.reason', 'like', "%{$search}%");
            }

            if (Schema::hasColumn('disputes', 'decision_action')) {
                $w->orWhere('disputes.decision_action', 'like', "%{$search}%");
            }

            $w->orWhere('reservations.status', 'like', "%{$search}%")
              ->orWhere('reservations.settlement_status', 'like', "%{$search}%")
              ->orWhere('services.title', 'like', "%{$search}%")
              ->orWhereRaw(
                  "TRIM(CONCAT(COALESCE(dispute_clients.first_name,''), ' ', COALESCE(dispute_clients.last_name,''))) like ?",
                  ["%{$search}%"]
              )
              ->orWhereRaw(
                  "TRIM(CONCAT(COALESCE(dispute_coaches.first_name,''), ' ', COALESCE(dispute_coaches.last_name,''))) like ?",
                  ["%{$search}%"]
              );
        });
    }

    $selects = [
        'disputes.id as dispute_id',
        'disputes.reservation_id',
        'disputes.status as dispute_status',
        'disputes.opened_by_role',
        'disputes.created_at as dispute_created_at',
        'disputes.updated_at as dispute_updated_at',

        'reservations.status as reservation_status',
        'reservations.settlement_status',
        'reservations.total_minor',
        'reservations.currency',

        DB::raw("COALESCE(services.title, reservations.service_title_snapshot, 'Unknown Service') as service_title"),
        DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(dispute_clients.first_name,''), ' ', COALESCE(dispute_clients.last_name,''))), ''), 'Unknown Client') as client_name"),
        DB::raw("COALESCE(NULLIF(TRIM(CONCAT(COALESCE(dispute_coaches.first_name,''), ' ', COALESCE(dispute_coaches.last_name,''))), ''), 'Unknown Coach') as coach_name"),
        DB::raw("COALESCE(disputes.resolved_at, disputes.decided_at, disputes.updated_at, disputes.created_at) as dispute_last_event_at"),
    ];

    if (Schema::hasColumn('disputes', 'reason')) {
        $selects[] = 'disputes.reason';
    }

    if (Schema::hasColumn('disputes', 'decision_action')) {
        $selects[] = 'disputes.decision_action';
    }

    if (Schema::hasColumn('disputes', 'resolved_at')) {
        $selects[] = 'disputes.resolved_at';
    }

    if (Schema::hasColumn('disputes', 'decided_at')) {
        $selects[] = 'disputes.decided_at';
    }

    return $query
        ->select($selects)
        ->orderByDesc(DB::raw('COALESCE(disputes.resolved_at, disputes.decided_at, disputes.updated_at, disputes.created_at)'))
        ->paginate(12, ['*'], 'disputes_page')
        ->withQueryString();
}
}