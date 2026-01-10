<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\DTOs\Staff\CreateStaffDTO;
use Illuminate\Foundation\Http\FormRequest;

final class StoreStaffRequest extends FormRequest
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
            'display_name' => ['required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'string', 'url', 'max:500'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:100'],
            'is_bookable' => ['boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ];
    }

    public function getDisplayName(): string
    {
        return $this->validated('display_name');
    }

    public function getBio(): ?string
    {
        return $this->validated('bio');
    }

    public function getPhotoUrl(): ?string
    {
        return $this->validated('photo_url');
    }

    /**
     * @return array<int, string>|null
     */
    public function getSpecializations(): ?array
    {
        return $this->validated('specializations');
    }

    public function isBookable(): bool
    {
        return (bool) ($this->validated('is_bookable') ?? true);
    }

    /**
     * @return array<int, int>
     */
    public function getServiceIds(): array
    {
        $serviceIds = $this->validated('service_ids') ?? [];

        return array_map(static fn (mixed $id): int => (int) $id, $serviceIds);
    }

    public function toDTO(): CreateStaffDTO
    {
        return new CreateStaffDTO(
            displayName: $this->getDisplayName(),
            bio: $this->getBio(),
            photoUrl: $this->getPhotoUrl(),
            specializations: $this->getSpecializations(),
            isBookable: $this->isBookable(),
            serviceIds: $this->getServiceIds(),
        );
    }
}
