<?php

declare(strict_types=1);

namespace App\Actions\Service;

use App\Contracts\Repositories\ServiceRepository;
use App\DTOs\Service\IndexServicesDTO;
use App\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class IndexServicesAction
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Service>
     */
    public function handle(IndexServicesDTO $dto): LengthAwarePaginator
    {
        return $this->serviceRepository->paginateOrdered($dto->perPage);
    }
}
