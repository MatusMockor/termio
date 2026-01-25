<?php

declare(strict_types=1);

namespace App\DTOs\Onboarding;

/**
 * @property-read string $name
 * @property-read int $duration
 * @property-read float $price
 * @property-read string|null $description
 */
final readonly class ServiceTemplateDTO
{
    public function __construct(
        public string $name,
        public int $duration,
        public float $price,
        public ?string $description = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            duration: $data['duration'],
            price: $data['price'],
            description: $data['description'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'duration' => $this->duration,
            'price' => $this->price,
            'description' => $this->description,
        ];
    }
}
