<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

interface PublicBookingRead
{
    public function getTenantBySlug(string $tenantSlug): Tenant;

    /**
     * @return array{
     *     name: string,
     *     business_type: \App\Enums\BusinessType|null,
     *     address: string|null,
     *     phone: string|null,
     *     logo_url: string|null
     * }
     */
    public function getTenantInfo(string $tenantSlug): array;

    /**
     * @return Collection<int, Service>
     */
    public function getServices(string $tenantSlug): Collection;

    /**
     * @return Collection<int, StaffProfile>
     */
    public function getStaff(string $tenantSlug, ?int $serviceId = null): Collection;

    /**
     * @return Collection<int, Service>
     */
    public function getStaffServices(string $tenantSlug, int $staffId): Collection;

    /**
     * @return array<int, string>
     */
    public function getAvailableDates(
        string $tenantSlug,
        int $serviceId,
        int $month,
        int $year,
        ?int $staffId = null
    ): array;

    /**
     * @return array{id: int, display_name: string}|null
     */
    public function getStaffSummary(?int $staffId): ?array;
}
