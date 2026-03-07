<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\DTOs\Client\IndexClientsDTO;
use App\Enums\ClientBookingState;
use App\Enums\ClientRiskLevel;
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
            'booking_state' => ['nullable', Rule::enum(ClientBookingState::class)],
            'risk_level' => ['nullable', Rule::enum(ClientRiskLevel::class)],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:client_tags,id'],
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

    public function getBookingState(): ?ClientBookingState
    {
        $bookingState = $this->validated('booking_state');

        if (! is_string($bookingState)) {
            return null;
        }

        return ClientBookingState::tryFrom($bookingState);
    }

    public function getRiskLevel(): ?ClientRiskLevel
    {
        $riskLevel = $this->validated('risk_level');

        if (! is_string($riskLevel)) {
            return null;
        }

        return ClientRiskLevel::tryFrom($riskLevel);
    }

    /**
     * @return array<int, int>
     */
    public function getTagIds(): array
    {
        return array_map(static fn (mixed $tagId): int => (int) $tagId, $this->validated('tag_ids', []));
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
            bookingState: $this->getBookingState(),
            riskLevel: $this->getRiskLevel(),
            tagIds: $this->getTagIds(),
            perPage: $this->getPerPage(),
        );
    }
}
