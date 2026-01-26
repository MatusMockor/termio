<?php

declare(strict_types=1);

namespace App\Actions\Tenant;

use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class TenantLogoUploadAction
{
    private readonly string $disk;

    public function __construct()
    {
        $this->disk = config('filesystems.logo_disk', 'public');
    }

    public function handle(Tenant $tenant, UploadedFile $logo): Tenant
    {
        // Delete old logo if exists
        if ($tenant->logo && Storage::disk($this->disk)->exists($tenant->logo)) {
            Storage::disk($this->disk)->delete($tenant->logo);
        }

        // Store new logo
        $path = $logo->store('logos', $this->disk);

        // Update tenant
        $tenant->logo = $path;
        $tenant->save();

        return $tenant;
    }
}
