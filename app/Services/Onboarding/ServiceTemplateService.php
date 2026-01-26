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
            'salon' => [
                'type' => BusinessType::Salon,
                'label' => BusinessType::Salon->label(),
                'icon' => BusinessType::Salon->icon(),
                'description' => BusinessType::Salon->description(),
                'template_count' => count(config('service_templates.salon', [])),
            ],
            'barber' => [
                'type' => BusinessType::Barber,
                'label' => BusinessType::Barber->label(),
                'icon' => BusinessType::Barber->icon(),
                'description' => BusinessType::Barber->description(),
                'template_count' => count(config('service_templates.barber', [])),
            ],
            'beauty' => [
                'type' => BusinessType::Beauty,
                'label' => BusinessType::Beauty->label(),
                'icon' => BusinessType::Beauty->icon(),
                'description' => BusinessType::Beauty->description(),
                'template_count' => count(config('service_templates.beauty', [])),
            ],
            'massage' => [
                'type' => BusinessType::Massage,
                'label' => BusinessType::Massage->label(),
                'icon' => BusinessType::Massage->icon(),
                'description' => BusinessType::Massage->description(),
                'template_count' => count(config('service_templates.massage', [])),
            ],
            'fitness' => [
                'type' => BusinessType::Fitness,
                'label' => BusinessType::Fitness->label(),
                'icon' => BusinessType::Fitness->icon(),
                'description' => BusinessType::Fitness->description(),
                'template_count' => count(config('service_templates.fitness', [])),
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
