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

    /**
     * @return array<string, mixed>
     */
    public function getUpdateData(): array
    {
        $validated = $this->validated();

        if (array_key_exists('time_from', $validated) && is_string($validated['time_from'])) {
            $validated['time_from'] = $validated['time_from'].':00';
        }

        if (array_key_exists('time_to', $validated) && is_string($validated['time_to'])) {
            $validated['time_to'] = $validated['time_to'].':00';
        }

        return $validated;
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
