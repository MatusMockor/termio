<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointment;

use App\DTOs\Appointment\UpdateAppointmentDTO;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateAppointmentRequest extends FormRequest
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
        return [
            'client_id' => ['sometimes', 'exists:clients,id'],
            'service_id' => ['sometimes', 'exists:services,id'],
            'staff_id' => ['nullable', 'exists:staff_profiles,id'],
            'starts_at' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:pending,confirmed,in_progress,completed,cancelled,no_show'],
        ];
    }

    public function getClientId(): ?int
    {
        $clientId = $this->validated('client_id');

        return $clientId !== null ? (int) $clientId : null;
    }

    public function getServiceId(): ?int
    {
        $serviceId = $this->validated('service_id');

        return $serviceId !== null ? (int) $serviceId : null;
    }

    public function getStaffId(): ?int
    {
        $staffId = $this->validated('staff_id');

        return $staffId !== null ? (int) $staffId : null;
    }

    public function getStartsAt(): ?string
    {
        return $this->validated('starts_at');
    }

    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }

    public function getStatus(): ?string
    {
        return $this->validated('status');
    }

    public function hasStartsAt(): bool
    {
        return $this->has('starts_at');
    }

    public function hasServiceId(): bool
    {
        return $this->has('service_id');
    }

    public function toDTO(): UpdateAppointmentDTO
    {
        return new UpdateAppointmentDTO(
            clientId: $this->getClientId(),
            serviceId: $this->getServiceId(),
            staffId: $this->getStaffId(),
            startsAt: $this->getStartsAt(),
            notes: $this->getNotes(),
            status: $this->getStatus(),
            hasStartsAt: $this->hasStartsAt(),
            hasServiceId: $this->hasServiceId(),
        );
    }
}
