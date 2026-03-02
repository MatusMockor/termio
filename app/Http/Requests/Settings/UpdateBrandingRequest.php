<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

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
        $primaryColorRegex = config('branding.primary_color_regex');

        if (! is_string($primaryColorRegex) || $primaryColorRegex === '') {
            $primaryColorRegex = '/^#[0-9A-Fa-f]{6}$/';
        }

        return [
            'primary_color' => ['required', 'string', 'regex:'.$primaryColorRegex],
        ];
    }

    public function getPrimaryColor(): string
    {
        return (string) $this->validated('primary_color');
    }
}
