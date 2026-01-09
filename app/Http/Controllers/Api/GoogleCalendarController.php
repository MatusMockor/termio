<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GoogleCalendarController extends Controller
{
    public function __construct(
        private readonly GoogleCalendarService $calendarService
    ) {}

    public function status(): JsonResponse
    {
        $user = auth()->user();

        return response()->json([
            'connected' => $user->hasGoogleCalendarConnected(),
            'expires_at' => $user->google_token_expires_at,
        ]);
    }

    public function connect(): JsonResponse
    {
        $authUrl = $this->calendarService->getAuthUrl();

        return response()->json([
            'auth_url' => $authUrl,
        ]);
    }

    public function callback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        try {
            $tokens = $this->calendarService->exchangeCode($validated['code']);

            $user = auth()->user();
            $user->update([
                'google_access_token' => $tokens['access_token'],
                'google_refresh_token' => $tokens['refresh_token'] ?? $user->google_refresh_token,
                'google_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            ]);

            return response()->json([
                'message' => 'Google Calendar connected successfully.',
                'connected' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to connect Google Calendar.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function disconnect(): JsonResponse
    {
        $user = auth()->user();

        $user->update([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);

        return response()->json([
            'message' => 'Google Calendar disconnected successfully.',
            'connected' => false,
        ]);
    }
}
