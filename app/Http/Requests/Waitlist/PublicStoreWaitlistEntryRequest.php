<?php

declare(strict_types=1);

namespace App\Http\Requests\Waitlist;

use Illuminate\Foundation\Http\FormRequest;

final class PublicStoreWaitlistEntryRequest extends FormRequest
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
            'service_id' => ['required', 'integer'],
            'preferred_staff_id' => ['nullable', 'integer'],
            'preferred_date' => ['nullable', 'date'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i', 'after:time_from'],
            'client_name' => ['required', 'string', 'max:255'],
            'client_phone' => ['required', 'string', 'max:20'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWaitlistData(): array
    {
        $data = $this->validated();

        if (array_key_exists('time_from', $data) && is_string($data['time_from'])) {
            $data['time_from'] = $data['time_from'].':00';
        }

        if (array_key_exists('time_to', $data) && is_string($data['time_to'])) {
            $data['time_to'] = $data['time_to'].':00';
        }

        return $data;
    }

    public function getServiceId(): int
    {
        return (int) $this->validated('service_id');
    }

    public function getPreferredStaffId(): ?int
    {
        $value = $this->validated('preferred_staff_id');

        return $value !== null ? (int) $value : null;
    }
}
