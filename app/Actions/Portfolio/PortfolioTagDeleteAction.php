<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Contracts\Repositories\PortfolioTagRepository;
use App\Models\PortfolioTag;

final class PortfolioTagDeleteAction
{
    public function __construct(
        private readonly PortfolioTagRepository $repository,
    ) {}

    public function handle(PortfolioTag $tag): void
    {
        $this->repository->delete($tag);
    }
}
