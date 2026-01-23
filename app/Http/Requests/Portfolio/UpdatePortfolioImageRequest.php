<?php

declare(strict_types=1);

namespace App\Http\Requests\Portfolio;

use App\DTOs\Portfolio\UpdatePortfolioImageDTO;
use Illuminate\Foundation\Http\FormRequest;

final class UpdatePortfolioImageRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:portfolio_tags,id'],
            'is_public' => ['boolean'],
        ];
    }

    public function getTitle(): ?string
    {
        return $this->validated('title');
    }

    public function getDescription(): ?string
    {
        return $this->validated('description');
    }

    /**
     * @return array<int>
     */
    public function getTagIds(): array
    {
        return $this->validated('tag_ids') ?? [];
    }

    public function isPublic(): bool
    {
        return (bool) ($this->validated('is_public') ?? true);
    }

    public function toDTO(): UpdatePortfolioImageDTO
    {
        return new UpdatePortfolioImageDTO(
            title: $this->getTitle(),
            description: $this->getDescription(),
            tagIds: $this->getTagIds(),
            isPublic: $this->isPublic(),
        );
    }
}
