<?php

declare(strict_types=1);

namespace App\Actions\Service;

use App\Contracts\Repositories\ServiceRepository;
use App\DTOs\Service\UpdateServiceDTO;
use App\Models\Service;

final class ServiceUpdateAction
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
    ) {}

    public function handle(Service $service, UpdateServiceDTO $dto): Service
    {
        $data = array_filter([
            'name' => $dto->name,
            'description' => $dto->description,
            'duration_minutes' => $dto->durationMinutes,
            'price' => $dto->price,
            'category' => $dto->category,
            'is_active' => $dto->isActive,
            'is_bookable_online' => $dto->isBookableOnline,
        ], static fn (mixed $value): bool => $value !== null);

        return $this->serviceRepository->update($service, $data);
    }
}
