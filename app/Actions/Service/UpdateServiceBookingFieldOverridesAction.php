<?php

declare(strict_types=1);

namespace App\Actions\Service;

use App\Models\Service;
use App\Models\ServiceBookingFieldOverride;
use App\Services\Booking\Fields\BookingFieldResolverService;
use Illuminate\Support\Facades\DB;

final class UpdateServiceBookingFieldOverridesAction
{
    /**
     * @param  array<int, array{booking_field_id: int, is_enabled: bool, is_required: bool}>  $fields
     * @return array<int, array<string, mixed>>
     */
    public function execute(
        Service $service,
        array $fields,
        BookingFieldResolverService $resolver,
    ): array {
        DB::transaction(static function () use ($service, $fields): void {
            ServiceBookingFieldOverride::where('service_id', $service->id)->delete();

            foreach ($fields as $field) {
                ServiceBookingFieldOverride::create([
                    'service_id' => $service->id,
                    'booking_field_id' => $field['booking_field_id'],
                    'is_enabled' => $field['is_enabled'],
                    'is_required' => $field['is_enabled'] ? $field['is_required'] : false,
                ]);
            }
        });

        return $resolver->getServiceConfiguration($service->tenant, $service);
    }
}
