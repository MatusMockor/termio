<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Contracts\Repositories\ClientRepository;
use App\DTOs\Client\UpdateClientDTO;
use App\Models\Client;

final class ClientUpdateAction
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
    ) {}

    public function handle(Client $client, UpdateClientDTO $dto): Client
    {
        $data = array_filter([
            'name' => $dto->name,
            'phone' => $dto->phone,
            'email' => $dto->email,
            'notes' => $dto->notes,
            'status' => $dto->status,
        ], static fn (mixed $value): bool => $value !== null);

        return $this->clientRepository->update($client, $data);
    }
}
