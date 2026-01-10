<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use App\DTOs\Service\CreateServiceDTO;
use Illuminate\Foundation\Http\FormRequest;

final class StoreServiceRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'price' => ['required', 'numeric', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'is_bookable_online' => ['boolean'],
        ];
    }

    public function getName(): string
    {
        return $this->validated('name');
    }

    public function getDescription(): ?string
    {
        return $this->validated('description');
    }

    public function getDurationMinutes(): int
    {
        return (int) $this->validated('duration_minutes');
    }

    public function getPrice(): float
    {
        return (float) $this->validated('price');
    }

    public function getCategory(): ?string
    {
        return $this->validated('category');
    }

    public function isActive(): bool
    {
        return (bool) ($this->validated('is_active') ?? true);
    }

    public function isBookableOnline(): bool
    {
        return (bool) ($this->validated('is_bookable_online') ?? true);
    }

    public function toDTO(): CreateServiceDTO
    {
        return new CreateServiceDTO(
            name: $this->getName(),
            description: $this->getDescription(),
            durationMinutes: $this->getDurationMinutes(),
            price: $this->getPrice(),
            category: $this->getCategory(),
            isActive: $this->isActive(),
            isBookableOnline: $this->isBookableOnline(),
        );
    }
}
