<?php

declare(strict_types=1);

namespace App\Http\Requests\Portfolio;

use App\DTOs\Portfolio\CreatePortfolioImageDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class StorePortfolioImageRequest extends FormRequest
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
            'staff_id' => ['required', 'integer', 'exists:staff_profiles,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:portfolio_tags,id'],
        ];
    }

    public function getStaffId(): int
    {
        return (int) $this->validated('staff_id');
    }

    public function getTitle(): ?string
    {
        return $this->validated('title');
    }

    public function getDescription(): ?string
    {
        return $this->validated('description');
    }

    public function getImage(): UploadedFile
    {
        return $this->file('image');
    }

    /**
     * @return array<int>
     */
    public function getTagIds(): array
    {
        return $this->validated('tag_ids') ?? [];
    }

    public function toDTO(): CreatePortfolioImageDTO
    {
        return new CreatePortfolioImageDTO(
            staffId: $this->getStaffId(),
            title: $this->getTitle(),
            description: $this->getDescription(),
            image: $this->getImage(),
            tagIds: $this->getTagIds(),
        );
    }
}
