<?php

declare(strict_types=1);

namespace App\Http\Requests\ServiceCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReorderServiceCategoriesRequest extends FormRequest
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
            'order' => ['required', 'array', 'min:1'],
            'order.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('service_categories', 'id')->where('tenant_id', $this->tenantId()),
            ],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function getOrder(): array
    {
        /** @var array<int, int|string> $order */
        $order = $this->validated('order', []);

        return array_map(static fn (int|string $id): int => (int) $id, $order);
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
