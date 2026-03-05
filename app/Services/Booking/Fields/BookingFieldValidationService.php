<?php

declare(strict_types=1);

namespace App\Services\Booking\Fields;

use Illuminate\Validation\ValidationException;

final class BookingFieldValidationService
{
    /**
     * @param  array<string, mixed>  $customFields
     * @param  array<int, array{key: string, type: string, is_required: bool, options: array<int, mixed>|null}>  $effectiveFields
     */
    public function validate(array $customFields, array $effectiveFields): void
    {
        $errors = [];
        $allowedKeys = [];

        foreach ($effectiveFields as $field) {
            $allowedKeys[] = $field['key'];
        }

        foreach (array_keys($customFields) as $inputKey) {
            if (in_array($inputKey, $allowedKeys, true)) {
                continue;
            }

            $errors['custom_fields.'.$inputKey] = ['This field is not supported for the selected service.'];
        }

        foreach ($effectiveFields as $field) {
            $key = $field['key'];
            $hasValue = array_key_exists($key, $customFields);
            $value = $customFields[$key] ?? null;

            if ($field['is_required'] && ! $this->hasFilledValue($value)) {
                $errors['custom_fields.'.$key] = ['This field is required.'];

                continue;
            }

            if (! $hasValue || $value === null) {
                continue;
            }

            $typeError = $this->validateType($field['type'], $value, $field['options']);

            if ($typeError !== null) {
                $errors['custom_fields.'.$key] = [$typeError];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function hasFilledValue(mixed $value): bool
    {
        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return $value !== [];
        }

        return $value !== null;
    }

    /**
     * @param  array<int, mixed>|null  $options
     */
    private function validateType(string $type, mixed $value, ?array $options): ?string
    {
        return match ($type) {
            'text', 'textarea' => is_string($value) ? null : 'Expected text value.',
            'number' => is_numeric($value) ? null : 'Expected numeric value.',
            'select' => $this->validateSelectValue($value, $options),
            'checkbox' => is_bool($value) ? null : 'Expected boolean value.',
            'date' => $this->validateDateValue($value),
            default => 'Unsupported field type.',
        };
    }

    /**
     * @param  array<int, mixed>|null  $options
     */
    private function validateSelectValue(mixed $value, ?array $options): ?string
    {
        if (! is_string($value)) {
            return 'Expected option value.';
        }

        if ($options === null || $options === []) {
            return 'No options configured for this field.';
        }

        return in_array($value, $options, true)
            ? null
            : 'Selected option is invalid.';
    }

    private function validateDateValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return 'Expected date value.';
        }

        $timestamp = strtotime($value);

        return $timestamp !== false
            ? null
            : 'Date format is invalid.';
    }
}
