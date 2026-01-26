<?php

declare(strict_types=1);

namespace App\Actions\Tenant;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;

final class TenantLogoDeleteAction
{
    private readonly string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.logo_disk', 'public');
    }

    public function handle(Tenant $tenant): Tenant
    {
        // Delete logo file if exists
        if ($tenant->logo && Storage::disk($this->disk)->exists($tenant->logo)) {
            Storage::disk($this->disk)->delete($tenant->logo);
        }

        // Set tenant logo to null
        $tenant->logo = null;
        $tenant->save();

        return $tenant;
    }
}
