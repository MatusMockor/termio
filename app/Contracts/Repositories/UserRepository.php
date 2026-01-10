<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\User;

interface UserRepository
{
    public function findByEmail(string $email): ?User;

    public function findByGoogleIdOrEmail(string $googleId, string $email): ?User;

    public function create(array $data): User;

    public function updateGoogleTokens(User $user, string $googleId, ?string $token, ?string $refreshToken): User;
}
