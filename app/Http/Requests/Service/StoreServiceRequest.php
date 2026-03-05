<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use App\DTOs\Service\CreateServiceDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreServiceRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'price' => ['required', 'numeric', 'min:0'],
            'category' => ['nullable', 'string', 'max:100'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('service_categories', 'id')->where('tenant_id', $this->tenantId()),
            ],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
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

    public function getCategoryId(): ?int
    {
        $value = $this->validated('category_id');

        return $value !== null ? (int) $value : null;
    }

    public function getPriority(): int
    {
        return (int) ($this->validated('priority') ?? 0);
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
            categoryId: $this->getCategoryId(),
            priority: $this->getPriority(),
            isActive: $this->isActive(),
            isBookableOnline: $this->isBookableOnline(),
        );
    }

    private function tenantId(): int
    {
        $tenantId = $this->user()?->tenant_id;

        if (! is_int($tenantId)) {
            abort(401);
        }

        return $tenantId;
    }
}
