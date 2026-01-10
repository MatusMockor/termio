<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\DTOs\Settings\UpdateSettingsDTO;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateSettingsRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'business_type' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:20'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'settings' => ['sometimes', 'array'],
        ];
    }

    public function getName(): ?string
    {
        return $this->validated('name');
    }

    public function getBusinessType(): ?string
    {
        return $this->validated('business_type');
    }

    public function getAddress(): ?string
    {
        return $this->validated('address');
    }

    public function getPhone(): ?string
    {
        return $this->validated('phone');
    }

    public function getTimezone(): ?string
    {
        return $this->validated('timezone');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSettings(): ?array
    {
        return $this->validated('settings');
    }

    public function toDTO(): UpdateSettingsDTO
    {
        return new UpdateSettingsDTO(
            name: $this->getName(),
            businessType: $this->getBusinessType(),
            address: $this->getAddress(),
            phone: $this->getPhone(),
            timezone: $this->getTimezone(),
            settings: $this->getSettings(),
        );
    }
}
