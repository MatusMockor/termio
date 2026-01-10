<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Contracts\Repositories\TenantRepository;
use App\Contracts\Repositories\UserRepository;
use App\DTOs\Auth\GoogleUserDTO;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuthGoogleCallbackAction
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TenantRepository $tenantRepository,
    ) {}

    /**
     * @return array{user: User, tenant: Tenant|null, token: string}
     */
    public function handle(GoogleUserDTO $dto): array
    {
        return DB::transaction(function () use ($dto): array {
            $user = $this->userRepository->findByGoogleIdOrEmail($dto->id, $dto->email);

            if (! $user) {
                $tenant = $this->tenantRepository->create([
                    'name' => $dto->name."'s Business",
                    'slug' => Str::slug($dto->name).'-'.Str::random(6),
                ]);

                $user = $this->userRepository->create([
                    'tenant_id' => $tenant->id,
                    'name' => $dto->name,
                    'email' => $dto->email,
                    'google_id' => $dto->id,
                    'google_access_token' => $dto->token,
                    'google_refresh_token' => $dto->refreshToken,
                    'role' => 'owner',
                ]);
            } elseif (! $user->wasRecentlyCreated) {
                $this->userRepository->updateGoogleTokens(
                    $user,
                    $dto->id,
                    $dto->token,
                    $dto->refreshToken
                );
            }

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user,
                'tenant' => $user->tenant,
                'token' => $token,
            ];
        });
    }
}
