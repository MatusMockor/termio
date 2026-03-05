<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use App\DTOs\Service\UpdateServiceDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateServiceRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'duration_minutes' => ['sometimes', 'required', 'integer', 'min:5', 'max:480'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
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

    public function getName(): ?string
    {
        return $this->validated('name');
    }

    public function getDescription(): ?string
    {
        return $this->validated('description');
    }

    public function getDurationMinutes(): ?int
    {
        $value = $this->validated('duration_minutes');

        return $value !== null ? (int) $value : null;
    }

    public function getPrice(): ?float
    {
        $value = $this->validated('price');

        return $value !== null ? (float) $value : null;
    }

    public function getCategory(): ?string
    {
        return $this->validated('category');
    }

    public function hasCategoryId(): bool
    {
        return $this->has('category_id');
    }

    public function getCategoryId(): ?int
    {
        $value = $this->validated('category_id');

        return $value !== null ? (int) $value : null;
    }

    public function hasPriority(): bool
    {
        return $this->has('priority');
    }

    public function getPriority(): ?int
    {
        $value = $this->validated('priority');

        return $value !== null ? (int) $value : null;
    }

    public function isActive(): ?bool
    {
        $value = $this->validated('is_active');

        return $value !== null ? (bool) $value : null;
    }

    public function isBookableOnline(): ?bool
    {
        $value = $this->validated('is_bookable_online');

        return $value !== null ? (bool) $value : null;
    }

    public function toDTO(): UpdateServiceDTO
    {
        return new UpdateServiceDTO(
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

        return is_int($tenantId) ? $tenantId : 0;
    }
}
