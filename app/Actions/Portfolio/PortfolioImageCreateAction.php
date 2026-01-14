<?php

declare(strict_types=1);

namespace App\Actions\Portfolio;

use App\Contracts\Repositories\PortfolioImageRepository;
use App\Contracts\Services\ImageUploadService;
use App\DTOs\Portfolio\CreatePortfolioImageDTO;
use App\Models\PortfolioImage;
use Illuminate\Support\Facades\DB;

final class PortfolioImageCreateAction
{
    public function __construct(
        private readonly ImageUploadService $uploadService,
        private readonly PortfolioImageRepository $repository,
    ) {}

    public function handle(CreatePortfolioImageDTO $dto, int $tenantId): PortfolioImage
    {
        return DB::transaction(function () use ($dto, $tenantId): PortfolioImage {
            $uploaded = $this->uploadService->upload($dto->image, $tenantId, $dto->staffId);
            $maxOrder = $this->repository->getMaxSortOrder($dto->staffId);

            $image = $this->repository->create([
                'tenant_id' => $tenantId,
                'staff_id' => $dto->staffId,
                'title' => $dto->title,
                'description' => $dto->description,
                'file_path' => $uploaded->filePath,
                'file_name' => $uploaded->fileName,
                'file_size' => $uploaded->fileSize,
                'mime_type' => $uploaded->mimeType,
                'disk' => $uploaded->disk,
                'sort_order' => $maxOrder + 1,
                'is_public' => true,
            ]);

            if ($dto->tagIds !== []) {
                $image->tags()->sync($dto->tagIds);
            }

            return $image->load('tags');
        });
    }
}
