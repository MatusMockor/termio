<?php

declare(strict_types=1);

namespace App\Http\Requests\Waitlist;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ConvertWaitlistEntryRequest extends FormRequest
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
            'starts_at' => ['required', 'date'],
            'staff_id' => [
                'nullable',
                'integer',
                Rule::exists('staff_profiles', 'id')->where('tenant_id', $this->tenantId()),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function getStartsAt(): string
    {
        return (string) $this->validated('starts_at');
    }

    public function getStaffId(): ?int
    {
        $value = $this->validated('staff_id');

        return $value !== null ? (int) $value : null;
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
