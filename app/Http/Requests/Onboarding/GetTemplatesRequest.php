<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GetTemplatesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'businessType' => [
                'required',
                Rule::enum(BusinessType::class),
            ],
        ];
    }

    public function getBusinessType(): BusinessType
    {
        return BusinessType::from($this->validated('businessType'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'businessType' => $this->route('businessType'),
        ]);
    }
}
