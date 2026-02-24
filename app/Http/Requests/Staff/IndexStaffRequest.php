<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

final class IndexStaffRequest extends FormRequest
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
        $maximum = (int) config('pagination.staff.max');

        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', "max:{$maximum}"],
        ];
    }

    public function getPerPage(): int
    {
        $value = $this->validated('per_page');

        if (! $value) {
            return (int) config('pagination.staff.default');
        }

        return (int) $value;
    }
}
