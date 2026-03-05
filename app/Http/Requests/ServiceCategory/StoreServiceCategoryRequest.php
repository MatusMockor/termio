<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreServiceCategoryRequest extends FormRequest
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

    public function getName(): string
    {
        return (string) $this->validated('name');
    }

    public function getParentId(): ?int
    {
        $value = $this->validated('parent_id');

        return $value !== null ? (int) $value : null;
    }

    public function getPriority(): int
    {
        return (int) ($this->validated('priority') ?? 0);
    }

    public function getSortOrder(): int
    {
        return (int) ($this->validated('sort_order') ?? 0);
    }

    public function isActive(): bool
    {
        return (bool) ($this->validated('is_active') ?? true);
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
