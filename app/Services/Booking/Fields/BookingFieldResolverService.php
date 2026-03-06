<?php

declare(strict_types=1);

namespace App\Services\Booking\Fields;

use App\Models\BookingField;
use App\Models\Service;
use App\Models\ServiceBookingFieldOverride;
use App\Models\Tenant;

final class BookingFieldResolverService
{
    /**
     * @return array<int, array{
     *     id: int,
     *     key: string,
     *     label: string,
     *     type: string,
     *     options: array<int, mixed>|null,
     *     base: array{
     *         is_required: bool,
     *         is_active: bool,
     *         sort_order: int
     *     },
     *     override: array{
     *         is_enabled: bool,
     *         is_required: bool
     *     }|null,
     *     effective: array{
     *         is_enabled: bool,
     *         is_required: bool,
     *         is_active: bool,
     *         sort_order: int
     *     }
     * }>
     */
    public function getServiceConfiguration(Tenant $tenant, Service $service): array
    {
        $fields = BookingField::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->ordered()
            ->get();

        $overrides = ServiceBookingFieldOverride::where('service_id', $service->id)
            ->get()
            ->keyBy('booking_field_id');

        $resolved = [];

        foreach ($fields as $field) {
            /** @var ServiceBookingFieldOverride|null $override */
            $override = $overrides->get($field->id);
            $overrideIsEnabled = $override !== null ? $override->is_enabled : true;
            $overrideIsRequired = $override !== null ? $override->is_required : $field->is_required;
            $isEnabled = $field->is_active && $overrideIsEnabled;
            $isRequired = $isEnabled
                ? $overrideIsRequired
                : false;

            $resolved[] = [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type->value,
                'options' => $field->options,
                'base' => [
                    'is_required' => $field->is_required,
                    'is_active' => $field->is_active,
                    'sort_order' => $field->sort_order,
                ],
                'override' => $override !== null
                    ? [
                        'is_enabled' => $override->is_enabled,
                        'is_required' => $override->is_enabled ? $override->is_required : false,
                    ]
                    : null,
                'effective' => [
                    'is_enabled' => $isEnabled,
                    'is_required' => $isRequired,
                    'is_active' => $field->is_active,
                    'sort_order' => $field->sort_order,
                ],
            ];
        }

        return $resolved;
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     key: string,
     *     label: string,
     *     type: string,
     *     options: array<int, mixed>|null,
     *     is_required: bool,
     *     is_active: bool,
     *     sort_order: int
     * }>
     */
    public function resolveForService(Tenant $tenant, Service $service): array
    {
        $configuration = $this->getServiceConfiguration($tenant, $service);

        return array_values(array_filter(
            array_map(static function (array $field): array {
                return [
                    'id' => $field['id'],
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'type' => $field['type'],
                    'options' => $field['options'],
                    'is_required' => $field['effective']['is_required'],
                    'is_enabled' => $field['effective']['is_enabled'],
                    'is_active' => $field['effective']['is_active'],
                    'sort_order' => $field['effective']['sort_order'],
                ];
            }, $configuration),
            static fn (array $field): bool => $field['is_active'] === true && $field['is_enabled'] === true,
        ));
    }
}
