<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

final readonly class CreateStaffDTO
{
    /**
     * @param  array<int, string>|null  $specializations
     * @param  array<int, int>  $serviceIds
     */
    public function __construct(
        public string $displayName,
        public ?string $bio,
        public ?string $photoUrl,
        public ?array $specializations,
        public bool $isBookable,
        public array $serviceIds,
    ) {}
}
