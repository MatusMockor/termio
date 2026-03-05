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
     *     is_required: bool,
     *     is_active: bool,
     *     sort_order: int
     * }>
     */
    public function resolveForService(Tenant $tenant, Service $service): array
    {
        $fields = BookingField::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->ordered()
            ->get();

        $overrides = ServiceBookingFieldOverride::where('service_id', $service->id)
            ->get()
            ->keyBy('booking_field_id');

        $resolved = [];

        foreach ($fields as $field) {
            /** @var ServiceBookingFieldOverride|null $override */
            $override = $overrides->get($field->id);

            if ($override !== null && ! $override->is_enabled) {
                continue;
            }

            $resolved[] = [
                'id' => $field->id,
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type->value,
                'options' => $field->options,
                'is_required' => $override !== null ? $override->is_required : $field->is_required,
                'is_active' => $field->is_active,
                'sort_order' => $field->sort_order,
            ];
        }

        return $resolved;
    }
}
