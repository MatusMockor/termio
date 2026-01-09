<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

final class StoreClientRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'notes' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:active,inactive,vip'],
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
        return $this->validated('status') ?? 'active';
    }
}
