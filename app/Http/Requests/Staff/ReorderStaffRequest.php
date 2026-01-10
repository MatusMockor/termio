<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderStaffRequest extends FormRequest
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
            'order' => ['required', 'array'],
            'order.*' => ['required', 'integer', 'exists:staff_profiles,id'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function getOrder(): array
    {
        return array_map(static fn (mixed $id): int => (int) $id, $this->validated('order'));
    }
}
