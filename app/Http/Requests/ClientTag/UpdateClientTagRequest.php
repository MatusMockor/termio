<?php

declare(strict_types=1);

namespace App\Http\Requests\ClientTag;

use App\Models\ClientTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateClientTagRequest extends FormRequest
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
        /** @var ClientTag $tag */
        $tag = $this->route('tag');
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('client_tags', 'name')
                    ->ignore($tag->id)
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId)->whereNull('deleted_at')),
            ],
            'color' => ['sometimes', 'required', 'string', 'regex:'.config('branding.primary_color_regex')],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array{name?: string, color?: string, sort_order?: int}
     */
    public function getUpdateData(): array
    {
        $validated = $this->validated();

        if (array_key_exists('sort_order', $validated)) {
            $validated['sort_order'] = (int) $validated['sort_order'];
        }

        return $validated;
    }
}
