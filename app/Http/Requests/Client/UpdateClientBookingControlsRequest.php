<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class UpdateClientBookingControlsRequest extends FormRequest
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
            'is_blacklisted' => ['required', 'boolean'],
            'is_whitelisted' => ['required', 'boolean'],
            'booking_control_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->isBlacklisted() || ! $this->isWhitelisted()) {
                return;
            }

            $validator->errors()->add('is_whitelisted', 'A client cannot be blacklisted and whitelisted at the same time.');
        });
    }

    public function isBlacklisted(): bool
    {
        return (bool) $this->boolean('is_blacklisted');
    }

    public function isWhitelisted(): bool
    {
        return (bool) $this->boolean('is_whitelisted');
    }

    public function getBookingControlNote(): ?string
    {
        return $this->validated('booking_control_note');
    }
}
