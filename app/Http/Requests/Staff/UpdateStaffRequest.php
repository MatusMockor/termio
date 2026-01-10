<?php

declare(strict_types=1);

namespace App\Http\Requests\Staff;

use App\DTOs\Staff\UpdateStaffDTO;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateStaffRequest extends FormRequest
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
            'display_name' => ['sometimes', 'required', 'string', 'max:255'],
            'bio' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'string', 'url', 'max:500'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:100'],
            'is_bookable' => ['boolean'],
            'service_ids' => ['nullable', 'array'],
            'service_ids.*' => ['integer', 'exists:services,id'],
        ];
    }

    public function getDisplayName(): ?string
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

    /**
     * @SuppressWarnings(PHPMD.BooleanGetMethodName)
     */
    public function getIsBookable(): ?bool
    {
        $value = $this->validated('is_bookable');

        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    /**
     * @return array<int, int>|null
     */
    public function getServiceIds(): ?array
    {
        $serviceIds = $this->validated('service_ids');

        if ($serviceIds === null) {
            return null;
        }

        return array_map(static fn (mixed $id): int => (int) $id, $serviceIds);
    }

    public function hasServiceIds(): bool
    {
        return $this->has('service_ids');
    }

    public function toDTO(): UpdateStaffDTO
    {
        return new UpdateStaffDTO(
            displayName: $this->getDisplayName(),
            bio: $this->getBio(),
            photoUrl: $this->getPhotoUrl(),
            specializations: $this->getSpecializations(),
            isBookable: $this->getIsBookable(),
            serviceIds: $this->getServiceIds(),
            hasServiceIds: $this->hasServiceIds(),
        );
    }
}
