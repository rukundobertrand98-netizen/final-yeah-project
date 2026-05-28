<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\BookingService;
use App\Services\MtnMomoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MtnMomoWebhookController extends Controller
{
    public function __invoke(Request $request, MtnMomoService $momo, BookingService $bookingService): JsonResponse
    {
        $reference = $request->header('X-Reference-Id')
            ?: $request->input('referenceId')
            ?: $request->input('externalId');

        if (! $reference) {
            return response()->json(['message' => 'Missing MTN reference.'], 422);
        }

        $payment = Payment::where('external_id', $reference)->firstOrFail();
        $payment = $momo->checkStatus($payment);

        if ($payment->status === 'successful') {
            $bookingService->confirm($payment->booking);
        }

        return response()->json(['status' => $payment->status]);
    }
}
