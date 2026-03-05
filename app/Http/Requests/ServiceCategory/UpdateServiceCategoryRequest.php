<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateServiceCategoryRequest extends FormRequest
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
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('service_categories', 'id')->where('tenant_id', $this->tenantId()),
            ],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function hasName(): bool
    {
        return $this->has('name');
    }

    public function getName(): ?string
    {
        $value = $this->validated('name');

        return is_string($value) ? $value : null;
    }

    public function hasParentId(): bool
    {
        return $this->has('parent_id');
    }

    public function getParentId(): ?int
    {
        $value = $this->validated('parent_id');

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

    public function hasSortOrder(): bool
    {
        return $this->has('sort_order');
    }

    public function getSortOrder(): ?int
    {
        $value = $this->validated('sort_order');

        return $value !== null ? (int) $value : null;
    }

    public function hasActiveFlag(): bool
    {
        return $this->has('is_active');
    }

    public function isActive(): ?bool
    {
        $value = $this->validated('is_active');

        return $value !== null ? (bool) $value : null;
    }

    private function tenantId(): int
    {
        $tenantId = $this->user()?->tenant_id;

        return is_int($tenantId) ? $tenantId : 0;
    }
}
