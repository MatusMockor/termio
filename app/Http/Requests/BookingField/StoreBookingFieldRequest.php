<?php

declare(strict_types=1);

namespace App\Http\Requests\BookingField;

use App\Enums\BookingFieldType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreBookingFieldRequest extends FormRequest
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
            'key' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('booking_fields', 'key')->where('tenant_id', $this->tenantId()),
            ],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(BookingFieldType::values())],
            'options' => ['nullable', 'array'],
            'options.*' => ['required_with:options', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function getKey(): string
    {
        return (string) $this->validated('key');
    }

    public function getLabel(): string
    {
        return (string) $this->validated('label');
    }

    public function getType(): BookingFieldType
    {
        return BookingFieldType::from((string) $this->validated('type'));
    }

    /**
     * @return array<int, string>|null
     */
    public function getOptions(): ?array
    {
        $options = $this->validated('options');

        return is_array($options) ? array_values($options) : null;
    }

    public function isRequired(): bool
    {
        return (bool) ($this->validated('is_required') ?? false);
    }

    public function isActive(): bool
    {
        return (bool) ($this->validated('is_active') ?? true);
    }

    public function getSortOrder(): int
    {
        return (int) ($this->validated('sort_order') ?? 0);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'key.regex' => 'The key may only contain lowercase letters, numbers and underscore.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(static function (Validator $validator): void {
            /** @var array<string, mixed> $data */
            $data = $validator->safe()->all();
            $type = $data['type'] ?? null;
            $options = $data['options'] ?? null;

            if (
                $type === BookingFieldType::Select->value
                && (! is_array($options) || ! $options)
            ) {
                $validator->errors()->add('options', 'Options are required for select fields.');

                return;
            }

            if ($type !== BookingFieldType::Select->value && $options !== null) {
                $validator->errors()->add('options', 'Options are allowed only for select fields.');
            }
        });
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
