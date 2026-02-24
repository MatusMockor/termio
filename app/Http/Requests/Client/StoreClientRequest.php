<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\DTOs\Client\CreateClientDTO;
use App\Enums\ClientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::enum(ClientStatus::class)],
        ];
    }

    public function getName(): string
    {
        return $this->validated('name');
    }

    public function getPhone(): ?string
    {
        return $this->validated('phone');
    }

    public function getEmail(): ?string
    {
        return $this->validated('email');
    }

    public function getNotes(): ?string
    {
        return $this->validated('notes');
    }

    public function getStatus(): string
    {
        return $this->validated('status') ?? ClientStatus::Active->value;
    }

    public function toDTO(): CreateClientDTO
    {
        return new CreateClientDTO(
            name: $this->getName(),
            phone: $this->getPhone(),
            email: $this->getEmail(),
            notes: $this->getNotes(),
            status: $this->getStatus(),
        );
    }
}
