<?php

declare(strict_types=1);

namespace App\DTOs\Staff;

final readonly class UpdateStaffDTO
{
    /**
     * @param  array<int, string>|null  $specializations
     * @param  array<int, int>|null  $serviceIds
     */
    public function __construct(
        public ?string $displayName,
        public ?string $bio,
        public ?string $photoUrl,
        public ?array $specializations,
        public ?bool $isBookable,
        public ?array $serviceIds,
        public bool $hasServiceIds,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->displayName !== null) {
            $data['display_name'] = $this->displayName;
        }

        if ($this->bio !== null) {
            $data['bio'] = $this->bio;
        }

        if ($this->photoUrl !== null) {
            $data['photo_url'] = $this->photoUrl;
        }

        if ($this->specializations !== null) {
            $data['specializations'] = $this->specializations;
        }

        if ($this->isBookable !== null) {
            $data['is_bookable'] = $this->isBookable;
        }

        return $data;
    }
}
