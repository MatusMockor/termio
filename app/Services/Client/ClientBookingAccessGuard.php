<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Exceptions\ClientBookingAccessException;
use App\Models\Client;
use App\Models\Tenant;

final class ClientBookingAccessGuard
{
    public function __construct(
        private readonly ClientIdentityResolver $identityResolver,
    ) {}

    public function ensureCanBook(Tenant $tenant, ?string $phone, ?string $email): ?Client
    {
        $client = $this->identityResolver->findMatchingClient($tenant, $phone, $email);

        if ($client === null || ! $client->is_blacklisted) {
            return $client;
        }

        throw ClientBookingAccessException::blacklisted();
    }
}
