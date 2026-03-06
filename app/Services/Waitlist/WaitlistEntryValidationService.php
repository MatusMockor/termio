<?php

declare(strict_types=1);

namespace App\Services\Waitlist;

use App\Models\StaffProfile;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

final class WaitlistEntryValidationService
{
    public function ensureStaffSupportsService(
        Tenant $tenant,
        int $serviceId,
        ?int $staffId,
        string $field = 'preferred_staff_id',
    ): void {
        if ($staffId === null) {
            return;
        }

        $staffExists = StaffProfile::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->bookable()
            ->forService($serviceId)
            ->where('id', $staffId)
            ->exists();

        if ($staffExists) {
            return;
        }

        throw ValidationException::withMessages([
            $field => ['The selected staff is invalid.'],
        ]);
    }

    public function ensurePreferredStaffSupportsService(
        Tenant $tenant,
        int $serviceId,
        ?int $preferredStaffId,
    ): void {
        $this->ensureStaffSupportsService($tenant, $serviceId, $preferredStaffId);
    }
}
