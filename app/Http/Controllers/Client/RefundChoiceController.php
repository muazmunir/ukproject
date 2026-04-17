<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\RefundChoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RefundChoiceController extends Controller
{
    public function choose(
        Request $request,
        Reservation $reservation,
        RefundChoiceService $svc
    ): JsonResponse|RedirectResponse {
        abort_unless((int) $reservation->client_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'refund_method' => [
                'required',
                'string',
                Rule::in(['wallet_credit', 'original_payment']),
            ],
        ]);

        $result = $svc->process(
            $reservation,
            (string) $data['refund_method'],
            (int) $request->user()->id
        );

        $ok      = (bool) ($result['ok'] ?? false);
        $pending = (bool) ($result['pending'] ?? false);
        $partial = (bool) ($result['partial'] ?? false);

        $message = (string) ($result['message'] ?? '');
        $error   = (string) ($result['error'] ?? '');

        // -----------------------------
        // JSON response
        // -----------------------------
        if ($request->expectsJson() || $request->wantsJson()) {
            $statusCode = 200;

            if ($pending) {
                $statusCode = 202;
            } elseif ($partial) {
                $statusCode = 409;
            } elseif (!$ok) {
                $statusCode = 422;
            }

            return response()->json([
                'ok'      => $ok,
                'pending' => $pending,
                'partial' => $partial,
                'message' => $message ?: $this->defaultMessage($ok, $pending, $partial),
                'error'   => $error ?: null,
                'data'    => [
                    'reservation_id' => $reservation->id,
                    'refund_status'  => $result['refund_status'] ?? null,
                ],
            ], $statusCode);
        }

        // -----------------------------
        // Redirect response
        // -----------------------------
        if ($pending) {
            return back()->with(
                'warning',
                $message ?: 'Refund is being processed.'
            );
        }

        if ($ok) {
            return back()->with(
                'ok',
                $message ?: 'Refund processed successfully.'
            );
        }

        if ($partial) {
            return back()->with(
                'warning',
                $message ?: 'Refund partially completed. You can retry the remaining unresolved amount.'
            );
        }

        return back()->with(
            'error',
            $message ?: $error ?: 'Refund failed.'
        );
    }

    private function defaultMessage(bool $ok, bool $pending, bool $partial): string
    {
        if ($pending) {
            return 'Refund is being processed.';
        }

        if ($ok) {
            return 'Refund processed successfully.';
        }

        if ($partial) {
            return 'Refund partially completed.';
        }

        return 'Refund failed.';
    }
}