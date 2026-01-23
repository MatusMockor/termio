<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Contracts\Repositories\PortfolioTagRepository;
use App\DTOs\Portfolio\CreatePortfolioTagDTO;
use App\Models\PortfolioTag;

final class PortfolioTagCreateAction
{
    public function __construct(
        private readonly PortfolioTagRepository $repository,
    ) {}

    public function handle(CreatePortfolioTagDTO $dto, int $tenantId): PortfolioTag
    {
        return $this->repository->create([
            'tenant_id' => $tenantId,
            'name' => $dto->name,
            'color' => $dto->color,
        ]);
    }
}
