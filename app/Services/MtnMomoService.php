<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MtnMomoService
{
    public function requestToPay(Booking $booking, string $phone): Payment
    {
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'method' => 'mtn_momo',
            'amount' => $booking->amount,
            'currency' => config('kbs.momo.currency', 'RWF'),
            'payer_phone' => $this->normalizePhone($phone),
            'status' => 'pending',
            'external_id' => (string) Str::uuid(),
        ]);

        if (config('kbs.momo.sandbox', true)) {
            return $this->simulateSandboxPayment($payment, $phone);
        }

        return $this->callCollectionApi($payment, $phone);
    }

    protected function simulateSandboxPayment(Payment $payment, string $phone): Payment
    {
        $payment->update([
            'status' => 'successful',
            'mtn_reference' => 'SANDBOX-'.strtoupper(Str::random(8)),
            'provider_response' => [
                'mode' => 'sandbox',
                'phone' => $payment->payer_phone,
                'message' => 'Simulated MTN MoMo payment for KBS Ltd.',
            ],
            'paid_at' => now(),
        ]);

        return $payment;
    }

    protected function callCollectionApi(Payment $payment, string $phone): Payment
    {
        $baseUrl = rtrim(config('kbs.momo.base_url'), '/');
        $subscriptionKey = config('kbs.momo.subscription_key');
        $apiUser = config('kbs.momo.api_user');
        $apiKey = config('kbs.momo.api_key');
        $targetEnvironment = config('kbs.momo.environment', 'mtnrwanda');
        $callbackUrl = config('kbs.momo.callback_url');

        try {
            if (! $baseUrl || ! $subscriptionKey || ! $apiUser || ! $apiKey) {
                throw new \RuntimeException('MTN MoMo live credentials are missing.');
            }

            $tokenResponse = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            ])->withBasicAuth($apiUser, $apiKey)
                ->post("{$baseUrl}/collection/token/");

            if (! $tokenResponse->successful()) {
                throw new \RuntimeException('Failed to obtain MTN MoMo access token.');
            }

            $accessToken = $tokenResponse->json('access_token');

            $requestResponse = Http::withHeaders([
                'Authorization' => 'Bearer '.$accessToken,
                'X-Reference-Id' => $payment->external_id,
                'X-Target-Environment' => $targetEnvironment,
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
                'Content-Type' => 'application/json',
                ...($callbackUrl ? ['X-Callback-Url' => $callbackUrl] : []),
            ])->timeout(20)->post("{$baseUrl}/collection/v1_0/requesttopay", [
                'amount' => (string) (int) $payment->amount,
                'currency' => $payment->currency,
                'externalId' => $payment->external_id,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $payment->payer_phone,
                ],
                'payerMessage' => 'KBS Bus Ticket '.$payment->booking->reference,
                'payeeNote' => 'KBS Ltd Kigali',
            ]);

            $payment->update([
                'provider_response' => $requestResponse->json() ?? ['status' => $requestResponse->status()],
                'status' => $requestResponse->status() === 202 ? 'processing' : 'failed',
            ]);
        } catch (\Throwable $e) {
            Log::error('MTN MoMo payment failed', ['error' => $e->getMessage()]);
            $payment->update(['status' => 'failed', 'provider_response' => ['error' => $e->getMessage()]]);
        }

        return $payment->fresh();
    }

    public function checkStatus(Payment $payment): Payment
    {
        if (config('kbs.momo.sandbox', true)) {
            return $payment;
        }

        $baseUrl = rtrim(config('kbs.momo.base_url'), '/');
        $subscriptionKey = config('kbs.momo.subscription_key');
        $apiUser = config('kbs.momo.api_user');
        $apiKey = config('kbs.momo.api_key');
        $targetEnvironment = config('kbs.momo.environment', 'mtnrwanda');

        try {
            $tokenResponse = Http::withHeaders([
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            ])->withBasicAuth($apiUser, $apiKey)
                ->post("{$baseUrl}/collection/token/");

            if (! $tokenResponse->successful()) {
                throw new \RuntimeException('Failed to obtain MTN MoMo access token.');
            }

            $statusResponse = Http::withHeaders([
                'Authorization' => 'Bearer '.$tokenResponse->json('access_token'),
                'X-Target-Environment' => $targetEnvironment,
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            ])->timeout(20)->get("{$baseUrl}/collection/v1_0/requesttopay/{$payment->external_id}");

            $status = strtolower((string) $statusResponse->json('status'));
            $payment->update([
                'status' => match ($status) {
                    'successful' => 'successful',
                    'failed', 'rejected', 'timeout' => 'failed',
                    default => 'processing',
                },
                'mtn_reference' => $statusResponse->json('financialTransactionId') ?: $payment->mtn_reference,
                'provider_response' => $statusResponse->json() ?? ['status' => $statusResponse->status()],
                'paid_at' => $status === 'successful' ? now() : $payment->paid_at,
                'checked_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('MTN MoMo status check failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);
            $payment->update([
                'checked_at' => now(),
                'provider_response' => array_merge($payment->provider_response ?? [], ['status_check_error' => $e->getMessage()]),
            ]);
        }

        return $payment->fresh();
    }

    protected function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '250')) {
            return $digits;
        }
        if (str_starts_with($digits, '0')) {
            return '250'.substr($digits, 1);
        }

        return '250'.$digits;
    }
}
