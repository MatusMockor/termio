<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\DTOs\Client\IndexClientsDTO;
use App\Enums\ClientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IndexClientsRequest extends FormRequest
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
        $maximum = (int) config('pagination.clients.max');

        return [
            'status' => ['nullable', Rule::enum(ClientStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', "max:{$maximum}"],
        ];
    }

    public function getStatus(): ?ClientStatus
    {
        $status = $this->validated('status');

        if (! is_string($status)) {
            return null;
        }

        return ClientStatus::tryFrom($status);
    }

    public function getPage(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }

    public function getPerPage(): int
    {
        $value = $this->validated('per_page');

        if (! $value) {
            return (int) config('pagination.clients.default');
        }

        return (int) $value;
    }

    public function toDTO(): IndexClientsDTO
    {
        return new IndexClientsDTO(
            status: $this->getStatus(),
            perPage: $this->getPerPage(),
        );
    }
}
