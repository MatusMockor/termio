<?php

declare(strict_types=1);

namespace App\DTOs\Reporting;

final readonly class ServiceRevenueDTO
{
    public function __construct(
        public int $serviceId,
        public string $serviceName,
        public ?string $category,
        public float $revenue,
        public int $count,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'service_id' => $this->serviceId,
            'service_name' => $this->serviceName,
            'category' => $this->category,
            'revenue' => round($this->revenue, 2),
            'count' => $this->count,
        ];
    }
}
