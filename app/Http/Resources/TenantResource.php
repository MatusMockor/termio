<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'business_type' => $this->business_type,
            'address' => $this->address,
            'phone' => $this->phone,
            'timezone' => $this->timezone,
            'settings' => $this->settings,
            'status' => $this->status,
        ];
    }
}
