<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\DTOs\Settings\UpdateBrandingDTO;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateBrandingRequest extends FormRequest
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
        /** @var string $primaryColorRegex */
        $primaryColorRegex = config('branding.primary_color_regex');

        return [
            'primary_color' => ['required', 'string', 'regex:'.$primaryColorRegex],
        ];
    }

    public function getPrimaryColor(): string
    {
        return (string) $this->validated('primary_color');
    }

    public function toDTO(): UpdateBrandingDTO
    {
        return new UpdateBrandingDTO(
            primaryColor: $this->getPrimaryColor(),
        );
    }
}
