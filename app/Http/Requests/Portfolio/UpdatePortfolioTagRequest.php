<?php

declare(strict_types=1);

namespace App\Http\Requests\Portfolio;

use App\DTOs\Portfolio\UpdatePortfolioTagDTO;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePortfolioTagRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function getName(): string
    {
        return $this->validated('name');
    }

    public function getColor(): ?string
    {
        return $this->validated('color');
    }

    public function toDTO(): UpdatePortfolioTagDTO
    {
        return new UpdatePortfolioTagDTO(
            name: $this->getName(),
            color: $this->getColor(),
        );
    }
}
