<?php

declare(strict_types=1);

namespace App\Http\Requests\BookingField;

use App\Enums\BookingFieldType;
use App\Models\BookingField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateBookingFieldRequest extends FormRequest
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
                'sometimes',
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('booking_fields', 'key')
                    ->where('tenant_id', $this->tenantId())
                    ->ignore($this->fieldId()),
            ],
            'label' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', Rule::in(BookingFieldType::values())],
            'options' => ['nullable', 'array'],
            'options.*' => ['required_with:options', 'string', 'max:255'],
            'is_required' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getUpdateData(): array
    {
        $validated = $this->validated();

        if (array_key_exists('options', $validated)) {
            $validated['options'] = is_array($validated['options']) ? array_values($validated['options']) : null;
        }

        return $validated;
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
        $validator->after(function (Validator $validator): void {
            /** @var array<string, mixed> $data */
            $data = $validator->safe()->all();
            $type = $data['type'] ?? $this->route('field')?->type?->value;
            $optionsProvided = array_key_exists('options', $data);
            $options = $data['options'] ?? null;

            if ($type === BookingFieldType::Select->value && (! $optionsProvided || ! is_array($options))) {
                $validator->errors()->add('options', 'Options are required for select fields.');

                return;
            }

            if ($type !== BookingFieldType::Select->value && $optionsProvided && $options !== null) {
                $validator->errors()->add('options', 'Options are allowed only for select fields.');
            }
        });
    }

    private function fieldId(): ?int
    {
        $field = $this->route('field');

        return $field instanceof BookingField ? $field->id : null;
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
