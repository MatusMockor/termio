<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Contracts\Repositories\PortfolioTagRepository;
use App\DTOs\Portfolio\UpdatePortfolioTagDTO;
use App\Models\PortfolioTag;

final class PortfolioTagUpdateAction
{
    public function __construct(
        private readonly PortfolioTagRepository $repository,
    ) {}

    public function handle(PortfolioTag $tag, UpdatePortfolioTagDTO $dto): PortfolioTag
    {
        return $this->repository->update($tag, [
            'name' => $dto->name,
            'color' => $dto->color,
        ]);
    }
}
