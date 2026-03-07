<?php

declare(strict_types=1);

namespace App\Services\Booking;

use App\Models\Client;
use App\Models\Tenant;
use App\Services\Client\ClientBookingAccessGuard;
use App\Services\Client\ClientIdentityResolver;

final class PublicBookingClientService
{
    public function __construct(
        private readonly ClientBookingAccessGuard $bookingAccessGuard,
        private readonly ClientIdentityResolver $identityResolver,
    ) {}

    public function ensureClientCanBook(Tenant $tenant, ?string $phone, ?string $email): ?Client
    {
        return $this->bookingAccessGuard->ensureCanBook($tenant, $phone, $email);
    }

    public function findOrCreateClient(
        Tenant $tenant,
        string $name,
        ?string $phone,
        ?string $email,
        ?Client $matchingClient,
    ): Client {
        return $this->identityResolver->findOrCreate(
            tenant: $tenant,
            name: $name,
            phone: $phone,
            email: $email,
            matchingClient: $matchingClient,
        );
    }
}
