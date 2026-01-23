<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Contracts\Repositories\PortfolioImageRepository;
use App\DTOs\Portfolio\UpdatePortfolioImageDTO;
use App\Models\PortfolioImage;
use Illuminate\Support\Facades\DB;

final class PortfolioImageUpdateAction
{
    public function __construct(
        private readonly PortfolioImageRepository $repository,
    ) {}

    public function handle(PortfolioImage $image, UpdatePortfolioImageDTO $dto): PortfolioImage
    {
        return DB::transaction(function () use ($image, $dto): PortfolioImage {
            $this->repository->update($image, [
                'title' => $dto->title,
                'description' => $dto->description,
                'is_public' => $dto->isPublic,
            ]);

            $image->tags()->sync($dto->tagIds);

            return $image->load('tags');
        });
    }
}
