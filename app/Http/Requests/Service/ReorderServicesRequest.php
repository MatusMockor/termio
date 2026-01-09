<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderServicesRequest extends FormRequest
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
            'order.*' => ['required', 'integer', 'exists:services,id'],
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
