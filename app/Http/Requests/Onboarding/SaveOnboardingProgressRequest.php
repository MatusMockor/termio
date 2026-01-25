<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

final class SaveOnboardingProgressRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'step' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
        ];
    }

    public function getStep(): string
    {
        return $this->validated('step');
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->validated('data');
    }
}
