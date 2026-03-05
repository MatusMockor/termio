<?php

declare(strict_types=1);

namespace App\Http\Requests\Waitlist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReplaceFromWaitlistRequest extends FormRequest
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
            'waitlist_entry_id' => [
                'required',
                'integer',
                Rule::exists('waitlist_entries', 'id')->where('tenant_id', $this->tenantId()),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function getWaitlistEntryId(): int
    {
        return (int) $this->validated('waitlist_entry_id');
    }

    public function getNotes(): ?string
    {
        $value = $this->validated('notes');

        return is_string($value) ? $value : null;
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
