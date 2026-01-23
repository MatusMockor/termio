<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Contracts\Repositories\TenantRepository;
use App\Contracts\Repositories\UserRepository;
use App\DTOs\Auth\RegisterUserDTO;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AuthUserRegisterAction
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TenantRepository $tenantRepository,
    ) {}

    /**
     * @return array{user: User, tenant: Tenant, token: string}
     */
    public function handle(RegisterUserDTO $dto): array
    {
        return DB::transaction(function () use ($dto): array {
            $tenant = $this->tenantRepository->create([
                'name' => $dto->businessName,
                'slug' => Str::slug($dto->businessName),
                'business_type' => $dto->businessType,
            ]);

            $user = $this->userRepository->create([
                'tenant_id' => $tenant->id,
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => $dto->password,
                'role' => 'owner',
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            return [
                'user' => $user,
                'tenant' => $tenant,
                'token' => $token,
            ];
        });
    }
}
