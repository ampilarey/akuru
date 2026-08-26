<?php

namespace App\Domains\Notifications\Http\Controllers;

use App\Domains\Notifications\Contracts\SmsSenderInterface;
use App\Domains\Notifications\Support\LiveSms;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsApiController extends Controller
{
    public function __construct(
        protected SmsSenderInterface $smsService
    ) {}

    public function health(): JsonResponse
    {
        if (! LiveSms::allowed()) {
            return response()->json([
                'status' => 'ok',
                'service' => 'sms',
                'driver' => 'log',
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $upstream = config('services.sms_gateway.upstream_url');
        $isHealthy = $upstream
            ? Http::timeout(5)->get($upstream.'/health')->successful()
            : true;

        return response()->json([
            'status' => $isHealthy ? 'ok' : 'degraded',
            'service' => 'sms',
            'timestamp' => now()->toIso8601String(),
        ], $isHealthy ? 200 : 503);
    }

    /**
     * Send a single SMS - forwards to upstream provider
     */
    public function send(Request $request): JsonResponse
    {
        if (! $this->validateApiKey($request)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key',
                'error_code' => 'INVALID_API_KEY',
            ], 401);
        }

        $validated = $request->validate([
            'to' => 'required|string|max:20',
            'message' => 'required|string|max:1600',
            'sender_id' => 'nullable|string|max:11',
            'type' => 'nullable|string|in:notification,otp,promotional',
        ]);

        if (! LiveSms::allowed()) {
            $logged = $this->smsService->sendSms($validated['to'], $validated['message'], [
                'type' => $validated['type'] ?? 'notification',
            ]);

            return response()->json([
                'success' => (bool) ($logged['success'] ?? false),
                'data' => ['id' => $logged['message_id'] ?? null, 'status' => 'logged'],
                'driver' => 'log',
            ]);
        }

        $upstream = config('services.sms_gateway.upstream_url');
        $upstreamKey = config('services.sms_gateway.upstream_api_key');

        if (! $upstream) {
            Log::warning('SMS upstream not configured - set SMS_UPSTREAM_URL in .env');

            return response()->json([
                'success' => false,
                'message' => 'SMS upstream not configured',
                'error_code' => 'UPSTREAM_NOT_CONFIGURED',
            ], 503);
        }

        $response = Http::timeout(30)
            ->withHeaders([
                'X-API-Key' => $upstreamKey ?: config('services.sms_gateway.api_key'),
                'Authorization' => 'Bearer '.($upstreamKey ?: config('services.sms_gateway.api_key')),
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->post(rtrim($upstream, '/').'/sms/send', [
                'to' => $validated['to'],
                'message' => $validated['message'],
                'sender_id' => $validated['sender_id'] ?? 'AKURU',
                'type' => $validated['type'] ?? 'notification',
            ]);

        if ($response->successful()) {
            $result = $response->json();

            return response()->json($result, 200);
        }

        $errorJson = $response->json();
        Log::warning('SMS API upstream failed', ['response' => $response->body()]);

        return response()->json([
            'success' => false,
            'message' => $errorJson['message'] ?? $response->body(),
            'error_code' => $errorJson['error_code'] ?? 'UPSTREAM_FAILED',
        ], 422);
    }

    protected function validateApiKey(Request $request): bool
    {
        $apiKey = config('services.sms_gateway.api_key');
        if (empty($apiKey)) {
            return false;
        }

        $provided = $request->header('X-API-Key')
            ?? $request->header('Authorization')
            ?? $request->bearerToken();
        if (is_string($provided) && str_starts_with($provided, 'Bearer ')) {
            $provided = substr($provided, 7);
        }

        return hash_equals($apiKey, trim($provided ?? ''));
    }
}
