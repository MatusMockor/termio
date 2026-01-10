<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\UserRepository;
use App\Models\User;

final class EloquentUserRepository implements UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByGoogleIdOrEmail(string $googleId, string $email): ?User
    {
        return User::where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();
    }

    public function create(array $data): User
    {
        return User::create($data);
    }

    public function updateGoogleTokens(User $user, string $googleId, ?string $token, ?string $refreshToken): User
    {
        $user->update([
            'google_id' => $googleId,
            'google_access_token' => $token,
            'google_refresh_token' => $refreshToken,
        ]);

        return $user;
    }
}
