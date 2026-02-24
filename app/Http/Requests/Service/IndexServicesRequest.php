<?php

declare(strict_types=1);

namespace App\Http\Requests\Service;

use App\DTOs\Service\IndexServicesDTO;
use Illuminate\Foundation\Http\FormRequest;

final class IndexServicesRequest extends FormRequest
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
        $maximum = (int) config('pagination.services.max');

        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', "max:{$maximum}"],
        ];
    }

    public function getPerPage(): int
    {
        $value = $this->validated('per_page');

        if (! $value) {
            return (int) config('pagination.services.default');
        }

        return (int) $value;
    }

    public function getPage(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function toDTO(): IndexServicesDTO
    {
        return new IndexServicesDTO(
            perPage: $this->getPerPage(),
        );
    }
}
