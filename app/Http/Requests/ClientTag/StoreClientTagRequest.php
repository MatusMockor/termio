<?php

declare(strict_types=1);

namespace App\Http\Requests\ClientTag;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreClientTagRequest extends FormRequest
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
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('client_tags', 'name')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'color' => ['required', 'string', 'regex:'.config('branding.primary_color_regex')],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function getName(): string
    {
        return $this->validated('name');
    }

    public function getColor(): string
    {
        return $this->validated('color');
    }

    public function getSortOrder(): int
    {
        return (int) ($this->validated('sort_order') ?? 0);
    }
}
