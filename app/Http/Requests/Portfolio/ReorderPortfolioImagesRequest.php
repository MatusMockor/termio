<?php

declare(strict_types=1);

namespace App\Http\Requests\Portfolio;

use Illuminate\Foundation\Http\FormRequest;

final class ReorderPortfolioImagesRequest extends FormRequest
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
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:portfolio_images,id'],
        ];
    }

    /**
     * @return array<int, int>
     */
    public function getOrder(): array
    {
        return $this->validated('order');
    }
}
