<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Models\ClientTag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SyncClientTagsRequest extends FormRequest
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
            'tag_ids' => ['required', 'array'],
            'tag_ids.*' => ['integer', 'distinct', 'exists:client_tags,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tagIds = $this->getTagIds();

            if ($tagIds === []) {
                return;
            }

            $tenantId = $this->user()?->tenant_id;
            $matchingCount = ClientTag::where('tenant_id', $tenantId)
                ->whereIn('id', $tagIds)
                ->count();

            if ($matchingCount === count($tagIds)) {
                return;
            }

            $validator->errors()->add('tag_ids', 'The selected tags are invalid.');
        });
    }

    /**
     * @return array<int, int>
     */
    public function getTagIds(): array
    {
        return array_map(static fn (mixed $id): int => (int) $id, $this->validated('tag_ids', []));
    }
}
