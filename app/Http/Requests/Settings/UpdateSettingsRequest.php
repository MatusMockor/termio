<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\DTOs\Settings\UpdateSettingsDTO;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $leadTimeMin = (int) config('reservation.limits.lead_time_hours.min');
        $leadTimeMax = (int) config('reservation.limits.lead_time_hours.max');
        $maxDaysMin = (int) config('reservation.limits.max_days_in_advance.min');
        $maxDaysMax = (int) config('reservation.limits.max_days_in_advance.max');
        $slotIntervalMin = (int) config('reservation.limits.slot_interval_minutes.min');
        $slotIntervalMax = (int) config('reservation.limits.slot_interval_minutes.max');
        $slotIntervalMultipleOf = (int) config('reservation.limits.slot_interval_minutes.multiple_of');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'reservation_lead_time_hours' => ['sometimes', 'required', 'integer', 'min:'.$leadTimeMin, 'max:'.$leadTimeMax],
            'reservation_max_days_in_advance' => ['sometimes', 'required', 'integer', 'min:'.$maxDaysMin, 'max:'.$maxDaysMax],
            'reservation_slot_interval_minutes' => [
                'sometimes',
                'required',
                'integer',
                'min:'.$slotIntervalMin,
                'max:'.$slotIntervalMax,
                'multiple_of:'.$slotIntervalMultipleOf,
            ],
            'settings' => ['sometimes', 'array'],
        ];
    }

    public function getName(): ?string
    {
        return $this->validated('name');
    }

    public function getBusinessType(): ?string
    {
        return $this->validated('business_type');
    }

    public function getAddress(): ?string
    {
        return $this->validated('address');
    }

    public function getPhone(): ?string
    {
        return $this->validated('phone');
    }

    public function getTimezone(): ?string
    {
        return $this->validated('timezone');
    }

    public function getReservationLeadTimeHours(): ?int
    {
        $value = $this->validated('reservation_lead_time_hours');

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    public function getReservationMaxDaysInAdvance(): ?int
    {
        $value = $this->validated('reservation_max_days_in_advance');

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    public function getReservationSlotIntervalMinutes(): ?int
    {
        $value = $this->validated('reservation_slot_interval_minutes');

        if ($value === null) {
            return null;
        }

        return (int) $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSettings(): ?array
    {
        return $this->validated('settings');
    }

    public function toDTO(): UpdateSettingsDTO
    {
        return new UpdateSettingsDTO(
            name: $this->getName(),
            businessType: $this->getBusinessType(),
            address: $this->getAddress(),
            phone: $this->getPhone(),
            timezone: $this->getTimezone(),
            reservationLeadTimeHours: $this->getReservationLeadTimeHours(),
            reservationMaxDaysInAdvance: $this->getReservationMaxDaysInAdvance(),
            reservationSlotIntervalMinutes: $this->getReservationSlotIntervalMinutes(),
            settings: $this->getSettings(),
        );
    }
}
