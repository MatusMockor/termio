<?php

declare(strict_types=1);

namespace App\Actions\Client;

use App\Contracts\Repositories\ClientRepository;
use App\DTOs\Client\IndexClientsDTO;
use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class IndexClientsAction
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    public function handle(IndexClientsDTO $dto): LengthAwarePaginator
    {
        return $this->clientRepository->paginate(
            status: $dto->status,
            bookingState: $dto->bookingState,
            riskLevel: $dto->riskLevel,
            tagIds: $dto->tagIds,
            perPage: $dto->perPage,
        );
    }
}
