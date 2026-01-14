<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Portfolio\UploadedImageDTO;
use App\Models\PortfolioImage;
use Illuminate\Http\UploadedFile;

interface ImageUploadService
{
    public function upload(UploadedFile $file, int $tenantId, int $staffId): UploadedImageDTO;

    public function delete(PortfolioImage $image): void;
}
