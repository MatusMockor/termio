<?php

declare(strict_types=1);

namespace App\Actions\Service;

use App\Contracts\Repositories\ServiceRepository;
use App\DTOs\Service\CreateServiceDTO;
use App\Models\Service;

final class ServiceCreateAction
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
    ) {}

    public function handle(CreateServiceDTO $dto): Service
    {
        $maxOrder = $this->serviceRepository->getMaxSortOrder();

        return $this->serviceRepository->create([
            'name' => $dto->name,
            'description' => $dto->description,
            'duration_minutes' => $dto->durationMinutes,
            'price' => $dto->price,
            'category' => $dto->category,
            'is_active' => $dto->isActive,
            'is_bookable_online' => $dto->isBookableOnline,
            'sort_order' => $maxOrder + 1,
        ]);
    }
}
