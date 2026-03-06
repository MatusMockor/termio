<?php

declare(strict_types=1);

namespace App\Http\Requests\Waitlist;

use App\Enums\WaitlistEntryStatus;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateWaitlistEntryRequest extends FormRequest
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
            'preferred_staff_id' => ['nullable', 'integer'],
            'preferred_date' => ['nullable', 'date'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i', 'after:time_from'],
            'client_name' => ['sometimes', 'required', 'string', 'max:255'],
            'client_phone' => ['sometimes', 'required', 'string', 'max:20'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    WaitlistEntryStatus::Pending->value,
                    WaitlistEntryStatus::Contacted->value,
                    WaitlistEntryStatus::Cancelled->value,
                ]),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $entry = $this->route('entry');

            if (! $entry instanceof WaitlistEntry) {
                return;
            }

            if (in_array($entry->status, [WaitlistEntryStatus::Converted, WaitlistEntryStatus::Cancelled], true)) {
                $validator->errors()->add('status', 'This waitlist entry can no longer be updated.');

                return;
            }

            $nextStatus = $this->validated('status');

            if (! is_string($nextStatus)) {
                return;
            }

            if ($this->isTransitionAllowed($entry->status, WaitlistEntryStatus::from($nextStatus))) {
                return;
            }

            $validator->errors()->add('status', 'The selected status transition is invalid.');
        });
    }

    public function hasPreferredStaffId(): bool
    {
        return $this->hasValidatedValue('preferred_staff_id');
    }

    public function getPreferredStaffId(): ?int
    {
        return $this->getIntegerOrNull('preferred_staff_id');
    }

    public function hasPreferredDate(): bool
    {
        return $this->hasValidatedValue('preferred_date');
    }

    public function getPreferredDate(): ?string
    {
        return $this->getStringOrNull('preferred_date');
    }

    public function hasTimeFrom(): bool
    {
        return $this->hasValidatedValue('time_from');
    }

    public function getTimeFrom(): ?string
    {
        return $this->getNormalizedTimeOrNull('time_from');
    }

    public function hasTimeTo(): bool
    {
        return $this->hasValidatedValue('time_to');
    }

    public function getTimeTo(): ?string
    {
        return $this->getNormalizedTimeOrNull('time_to');
    }

    public function hasClientName(): bool
    {
        return $this->hasValidatedValue('client_name');
    }

    public function getClientName(): ?string
    {
        return $this->getStringOrNull('client_name');
    }

    public function hasClientPhone(): bool
    {
        return $this->hasValidatedValue('client_phone');
    }

    public function getClientPhone(): ?string
    {
        return $this->getStringOrNull('client_phone');
    }

    public function hasClientEmail(): bool
    {
        return $this->hasValidatedValue('client_email');
    }

    public function getClientEmail(): ?string
    {
        return $this->getStringOrNull('client_email');
    }

    public function hasNotes(): bool
    {
        return $this->hasValidatedValue('notes');
    }

    public function getNotes(): ?string
    {
        return $this->getStringOrNull('notes');
    }

    public function hasStatus(): bool
    {
        return $this->hasValidatedValue('status');
    }

    public function getStatus(): ?WaitlistEntryStatus
    {
        $value = $this->getStringOrNull('status');

        return $value !== null ? WaitlistEntryStatus::from($value) : null;
    }

    private function hasValidatedValue(string $key): bool
    {
        return array_key_exists($key, $this->validated());
    }

    private function getIntegerOrNull(string $key): ?int
    {
        if (! $this->hasValidatedValue($key)) {
            return null;
        }

        $value = $this->validated($key);

        return is_numeric($value) ? (int) $value : null;
    }

    private function getStringOrNull(string $key): ?string
    {
        if (! $this->hasValidatedValue($key)) {
            return null;
        }

        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }

    private function getNormalizedTimeOrNull(string $key): ?string
    {
        $value = $this->getStringOrNull($key);

        return $value !== null ? $value.':00' : null;
    }

    private function isTransitionAllowed(WaitlistEntryStatus $currentStatus, WaitlistEntryStatus $nextStatus): bool
    {
        return match ($currentStatus) {
            WaitlistEntryStatus::Pending => in_array($nextStatus, [
                WaitlistEntryStatus::Pending,
                WaitlistEntryStatus::Contacted,
                WaitlistEntryStatus::Cancelled,
            ], true),
            WaitlistEntryStatus::Contacted => in_array($nextStatus, [
                WaitlistEntryStatus::Pending,
                WaitlistEntryStatus::Contacted,
                WaitlistEntryStatus::Cancelled,
            ], true),
            WaitlistEntryStatus::Converted,
            WaitlistEntryStatus::Cancelled => false,
        };
    }
}
