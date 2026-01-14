<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Contracts\Repositories\PortfolioImageRepository;

final class PortfolioImageReorderAction
{
    public function __construct(
        private readonly PortfolioImageRepository $repository,
    ) {}

    /**
     * @param  array<int, int>  $order  Array of image IDs in desired order
     */
    public function handle(array $order): void
    {
        foreach ($order as $position => $id) {
            $this->repository->updateSortOrder($id, $position);
        }
    }
}
