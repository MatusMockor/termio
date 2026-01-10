<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Auth\AuthGoogleCallbackAction;
use App\Actions\Auth\AuthUserLoginAction;
use App\Actions\Auth\AuthUserRegisterAction;
use App\DTOs\Auth\GoogleUserDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

final class AuthController extends Controller
{
    public function register(RegisterRequest $request, AuthUserRegisterAction $action): JsonResponse
    {
        $result = $action->handle($request->toDTO());

        return response()->json($result, 201);
    }

    public function login(LoginRequest $request, AuthUserLoginAction $action): JsonResponse
    {
        $result = $action->handle($request->toDTO());

        return response()->json($result);
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

    public function googleCallback(AuthGoogleCallbackAction $action): JsonResponse
    {
        /** @var GoogleProvider $driver */
        $driver = Socialite::driver('google');

        /** @var \Laravel\Socialite\Two\User $googleUser */
        $googleUser = $driver->stateless()->user();

        $dto = new GoogleUserDTO(
            id: $googleUser->getId(),
            name: $googleUser->getName(),
            email: $googleUser->getEmail(),
            token: $googleUser->token,
            refreshToken: $googleUser->refreshToken,
        );

        $result = $action->handle($dto);

        return response()->json($result);
    }
}
