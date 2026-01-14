<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Contracts\Repositories\PortfolioImageRepository;
use App\Contracts\Services\ImageUploadService;
use App\Models\PortfolioImage;
use Illuminate\Support\Facades\DB;

final class PortfolioImageDeleteAction
{
    public function __construct(
        private readonly ImageUploadService $uploadService,
        private readonly PortfolioImageRepository $repository,
    ) {}

    public function handle(PortfolioImage $image): void
    {
        DB::transaction(function () use ($image): void {
            $this->uploadService->delete($image);
            $this->repository->delete($image);
        });
    }
}
