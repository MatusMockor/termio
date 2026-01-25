<?php

declare(strict_types=1);

namespace App\DTOs\Onboarding;

/**
 * @property-read string $step
 * @property-read array<string, mixed> $data
 */
final readonly class SaveProgressDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public string $step,
        public array $data,
    ) {}
}
