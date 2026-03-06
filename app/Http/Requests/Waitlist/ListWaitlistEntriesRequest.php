<?php

declare(strict_types=1);

namespace App\Http\Requests\Waitlist;

use App\Enums\WaitlistEntrySource;
use App\Enums\WaitlistEntryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListWaitlistEntriesRequest extends FormRequest
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
        $maximum = (int) config('pagination.waitlist.max');

        return [
            'status' => ['nullable', Rule::in(WaitlistEntryStatus::values())],
            'source' => ['nullable', Rule::in(WaitlistEntrySource::values())],
            'service_id' => ['nullable', 'integer'],
            'preferred_staff_id' => ['nullable', 'integer'],
            'preferred_date' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', "max:{$maximum}"],
        ];
    }

    public function getStatus(): ?string
    {
        $value = $this->validated('status');

        return is_string($value) ? $value : null;
    }

    public function getSource(): ?string
    {
        $value = $this->validated('source');

        return is_string($value) ? $value : null;
    }

    public function getServiceId(): ?int
    {
        $value = $this->validated('service_id');

        return $value !== null ? (int) $value : null;
    }

    public function getPreferredStaffId(): ?int
    {
        $value = $this->validated('preferred_staff_id');

        return $value !== null ? (int) $value : null;
    }

    public function getPreferredDate(): ?string
    {
        $value = $this->validated('preferred_date');

        return is_string($value) ? $value : null;
    }

    public function getSearch(): ?string
    {
        $value = $this->validated('search');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function getPerPage(): int
    {
        $value = $this->validated('per_page');

        return $value !== null ? (int) $value : (int) config('pagination.waitlist.default');
    }
}
