<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Enums\BusinessType;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class ServiceTemplateService
{
    /**
     * Get service templates for a specific business type.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTemplatesForBusinessType(BusinessType $type): array
    {
        $templates = config('service_templates.'.$type->value, []);

        if (! $templates) {
            return config('service_templates.other', []);
        }

        return $templates;
    }

    /**
     * Apply templates to tenant and return service data ready for creation.
     *
     * @return Collection<int, array{tenant_id: int, name: mixed, duration_minutes: mixed, price: mixed, is_active: bool, created_at: \Illuminate\Support\Carbon, updated_at: \Illuminate\Support\Carbon}>
     */
    public function applyTemplatesToTenant(Tenant $tenant, BusinessType $type): Collection
    {
        $templates = $this->getTemplatesForBusinessType($type);

        return collect($templates)->map(static function (array $template) use ($tenant): array {
            return [
                'tenant_id' => $tenant->id,
                'name' => $template['name'],
                'duration_minutes' => $template['duration_minutes'],
                'price' => $template['price'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        });
    }

    /**
     * Get all available business types with their template counts.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAllBusinessTypesWithCounts(): array
    {
        return [
            'hair_beauty' => [
                'type' => BusinessType::HairBeauty,
                'label' => BusinessType::HairBeauty->label(),
                'icon' => BusinessType::HairBeauty->icon(),
                'description' => BusinessType::HairBeauty->description(),
                'template_count' => count(config('service_templates.hair_beauty', [])),
            ],
            'spa_wellness' => [
                'type' => BusinessType::SpaWellness,
                'label' => BusinessType::SpaWellness->label(),
                'icon' => BusinessType::SpaWellness->icon(),
                'description' => BusinessType::SpaWellness->description(),
                'template_count' => count(config('service_templates.spa_wellness', [])),
            ],
            'other' => [
                'type' => BusinessType::Other,
                'label' => BusinessType::Other->label(),
                'icon' => BusinessType::Other->icon(),
                'description' => BusinessType::Other->description(),
                'template_count' => count(config('service_templates.other', [])),
            ],
        ];
    }
}
