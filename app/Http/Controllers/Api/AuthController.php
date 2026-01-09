<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $tenant = Tenant::create([
            'name' => $request->getBusinessName(),
            'slug' => Str::slug($request->getBusinessName()).'-'.Str::random(6),
            'business_type' => $request->getBusinessType(),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $request->getName(),
            'email' => $request->getEmail(),
            'password' => $request->getPassword(),
            'role' => 'owner',
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'tenant' => $tenant,
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->getEmail())->first();

        if (! $user || ! Hash::check($request->getPassword(), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'tenant' => $user->tenant,
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function currentUser(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $user,
            'tenant' => $user->tenant,
        ]);
    }

    public function googleRedirect(): JsonResponse
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        $url = $driver
            ->scopes(['openid', 'profile', 'email'])
            ->stateless()
            ->redirect()
            ->getTargetUrl();

        return response()->json(['url' => $url]);
    }

    public function googleCallback(): JsonResponse
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        /** @var \Laravel\Socialite\Two\User $googleUser */
        $googleUser = $driver->stateless()->user();

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if (! $user) {
            $tenant = Tenant::create([
                'name' => $googleUser->getName()."'s Business",
                'slug' => Str::slug($googleUser->getName()).'-'.Str::random(6),
            ]);

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'google_access_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
                'role' => 'owner',
            ]);
        }

        if ($user->wasRecentlyCreated === false) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'google_access_token' => $googleUser->token,
                'google_refresh_token' => $googleUser->refreshToken,
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'tenant' => $user->tenant,
            'token' => $token,
        ]);
    }
}
