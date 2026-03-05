<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateServiceBookingFieldsRequest extends FormRequest
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
            'fields' => ['required', 'array'],
            'fields.*.booking_field_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('booking_fields', 'id')->where('tenant_id', $this->tenantId()),
            ],
            'fields.*.is_enabled' => ['required', 'boolean'],
            'fields.*.is_required' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<int, array{booking_field_id: int, is_enabled: bool, is_required: bool}>
     */
    public function getFields(): array
    {
        /** @var array<int, array{booking_field_id: int|string, is_enabled: bool|int|string, is_required: bool|int|string}> $fields */
        $fields = $this->validated('fields', []);

        return array_map(
            static fn (array $field): array => [
                'booking_field_id' => (int) $field['booking_field_id'],
                'is_enabled' => (bool) $field['is_enabled'],
                'is_required' => (bool) $field['is_required'],
            ],
            $fields,
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
