<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\DTOs\Booking\CreatePublicBookingDTO;
use Illuminate\Foundation\Http\FormRequest;

final class PublicCreateBookingRequest extends FormRequest
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
            'service_id' => ['required', 'integer'],
            'staff_id' => ['nullable', 'integer'],
            'starts_at' => ['required', 'date', 'after:now'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['required', 'string', 'max:20'],
            'client_email' => ['required', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
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

    public function getClientName(): string
    {
        return $this->validated('client_name');
    }

    public function getClientPhone(): string
    {
        return $this->validated('client_phone');
    }

    public function getClientEmail(): string
    {
        return $this->validated('client_email');
    }

    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }

    public function toDTO(): CreatePublicBookingDTO
    {
        return new CreatePublicBookingDTO(
            serviceId: $this->getServiceId(),
            staffId: $this->getStaffId(),
            startsAt: $this->getStartsAt(),
            clientName: $this->getClientName(),
            clientPhone: $this->getClientPhone(),
            clientEmail: $this->getClientEmail(),
            notes: $this->getNotes(),
        );
    }
}
