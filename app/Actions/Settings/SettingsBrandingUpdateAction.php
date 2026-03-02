<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Contracts\Repositories\TenantRepository;
use App\Models\Tenant;

final class SettingsBrandingUpdateAction
{
    public function __construct(
        private readonly TenantRepository $tenantRepository,
    ) {}

    public function handle(Tenant $tenant, string $primaryColor): Tenant
    {
        $settings = $tenant->settings;

        $branding = $settings['branding'] ?? [];

        if (! is_array($branding)) {
            $branding = [];
        }

        $branding['primary_color'] = $primaryColor;
        $settings['branding'] = $branding;

        return $this->tenantRepository->update($tenant, [
            'settings' => $settings,
        ]);
    }
}
