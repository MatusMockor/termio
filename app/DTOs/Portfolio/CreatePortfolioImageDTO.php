<?php

declare(strict_types=1);

namespace App\DTOs\Portfolio;

use Illuminate\Http\UploadedFile;

final readonly class CreatePortfolioImageDTO
{
    /**
     * @param  array<int>  $tagIds
     */
    public function __construct(
        public int $staffId,
        public ?string $title,
        public ?string $description,
        public UploadedFile $image,
        public array $tagIds = [],
    ) {}
}
