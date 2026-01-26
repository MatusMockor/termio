<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class UploadLogoRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,svg', 'max:2048', 'dimensions:max_width=2000,max_height=2000'],
        ];
    }

    public function getLogo(): UploadedFile
    {
        return $this->validated('logo');
    }
}
