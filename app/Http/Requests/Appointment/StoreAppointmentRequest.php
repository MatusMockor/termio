<?php

declare(strict_types=1);

namespace App\Http\Requests\Appointment;

use App\DTOs\Appointment\CreateAppointmentDTO;
use Illuminate\Foundation\Http\FormRequest;

final class StoreAppointmentRequest extends FormRequest
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
            'client_id' => ['required', 'exists:clients,id'],
            'service_id' => ['required', 'exists:services,id'],
            'staff_id' => ['nullable', 'exists:staff_profiles,id'],
            'starts_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:pending,confirmed'],
            'source' => ['sometimes', 'in:online,manual,phone'],
        ];
    }

    public function getClientId(): int
    {
        return (int) $this->validated('client_id');
    }

    public function getServiceId(): int
    {
        return (int) $this->validated('service_id');
    }

    public function getStaffId(): ?int
    {
        $staffId = $this->validated('staff_id');

        return $staffId !== null ? (int) $staffId : null;
    }

    public function getStartsAt(): string
    {
        return $this->validated('starts_at');
    }

    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }

    public function getStatus(): string
    {
        return $this->validated('status') ?? 'confirmed';
    }

    public function getSource(): string
    {
        return $this->validated('source') ?? 'manual';
    }

    public function toDTO(): CreateAppointmentDTO
    {
        return new CreateAppointmentDTO(
            clientId: $this->getClientId(),
            serviceId: $this->getServiceId(),
            staffId: $this->getStaffId(),
            startsAt: $this->getStartsAt(),
            notes: $this->getNotes(),
            status: $this->getStatus(),
            source: $this->getSource(),
        );
    }
}
