<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Contracts\Repositories\ClientRepository;
use App\DTOs\Client\CreateClientDTO;
use App\Models\Client;

final class ClientCreateAction
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
    ) {}

    public function handle(CreateClientDTO $dto): Client
    {
        return $this->clientRepository->create([
            'name' => $dto->name,
            'phone' => $dto->phone,
            'email' => $dto->email,
            'notes' => $dto->notes,
            'status' => $dto->status,
        ]);
    }
}
