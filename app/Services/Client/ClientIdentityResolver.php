<?php

declare(strict_types=1);

namespace App\Services\Client;

use App\Models\Client;
use App\Models\Tenant;
use App\Support\ClientIdentityNormalizer;

final class ClientIdentityResolver
{
    public function findMatchingClient(Tenant $tenant, ?string $phone, ?string $email): ?Client
    {
        $normalizedPhone = ClientIdentityNormalizer::normalizePhone($phone);

        if ($normalizedPhone !== null) {
            return Client::withoutTenantScope()
                ->where('tenant_id', $tenant->id)
                ->where('phone_normalized', $normalizedPhone)
                ->first();
        }

        $normalizedEmail = ClientIdentityNormalizer::normalizeEmail($email);

        if ($normalizedEmail === null) {
            return null;
        }

        return Client::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('email_normalized', $normalizedEmail)
            ->first();
    }

    public function findOrCreate(
        Tenant $tenant,
        string $name,
        ?string $phone,
        ?string $email,
        ?Client $matchingClient = null,
    ): Client {
        $client = $matchingClient ?? $this->findMatchingClient($tenant, $phone, $email);

        if ($client !== null) {
            return $client;
        }

        return Client::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
        ]);
    }
}
