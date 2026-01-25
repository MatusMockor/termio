<?php

declare(strict_types=1);

namespace App\Http\Requests\Onboarding;

use App\Enums\BusinessType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StartOnboardingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_type' => ['required', 'string', Rule::in(['hair_beauty', 'spa_wellness', 'other'])],
        ];
    }

    public function getBusinessType(): BusinessType
    {
        return BusinessType::from($this->validated('business_type'));
    }
}
