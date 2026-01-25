<?php

declare(strict_types=1);

namespace App\DTOs\Onboarding;

use App\Enums\BusinessType;

/**
 * @property-read bool $completed
 * @property-read BusinessType|null $businessType
 * @property-read string|null $currentStep
 * @property-read array<string, mixed> $data
 * @property-read string|null $completedAt
 */
final readonly class OnboardingStatusDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public bool $completed,
        public ?BusinessType $businessType,
        public ?string $currentStep,
        public array $data,
        public ?string $completedAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            completed: $data['completed'],
            businessType: isset($data['business_type']) ? BusinessType::from($data['business_type']) : null,
            currentStep: $data['current_step'] ?? null,
            data: $data['data'] ?? [],
            completedAt: $data['completed_at'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'completed' => $this->completed,
            'business_type' => $this->businessType?->value,
            'current_step' => $this->currentStep,
            'data' => $this->data,
            'completed_at' => $this->completedAt,
        ];
    }
}
