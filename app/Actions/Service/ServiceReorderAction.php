<?php

declare(strict_types=1);

namespace App\Actions\Service;

use App\Models\Service;
use App\Services\Shared\SortOrderService;

final class ServiceReorderAction
{
    public function __construct(
        private readonly SortOrderService $sortOrderService,
    ) {}

    /**
     * @param  array<int, int>  $order
     */
    public function handle(array $order): void
    {
        $this->sortOrderService->reorder(Service::class, $order);
    }
}
