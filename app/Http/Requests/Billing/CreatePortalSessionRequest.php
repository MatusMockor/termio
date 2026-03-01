<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use Illuminate\Foundation\Http\FormRequest;

final class CreatePortalSessionRequest extends FormRequest
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
            'return_url' => ['required', 'string', 'url', 'max:2048'],
        ];
    }

    public function getReturnUrl(): string
    {
        return (string) $this->validated('return_url');
    }
}
