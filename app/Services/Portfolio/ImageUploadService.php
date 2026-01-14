<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Contracts\Services\ImageUploadService as ImageUploadServiceContract;
use App\DTOs\Portfolio\UploadedImageDTO;
use App\Models\PortfolioImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class ImageUploadService implements ImageUploadServiceContract
{
    public function upload(UploadedFile $file, int $tenantId, int $staffId): UploadedImageDTO
    {
        $disk = $this->getDisk();
        $path = $this->generatePath($tenantId, $staffId);
        $fileName = $this->generateFileName($file);

        Storage::disk($disk)->putFileAs($path, $file, $fileName);

        return new UploadedImageDTO(
            filePath: $path.'/'.$fileName,
            fileName: $fileName,
            fileSize: $file->getSize(),
            mimeType: $file->getMimeType() ?? 'image/jpeg',
            disk: $disk,
        );
    }

    public function delete(PortfolioImage $image): void
    {
        Storage::disk($image->disk)->delete($image->file_path);
    }

    private function getDisk(): string
    {
        return config('filesystems.portfolio_disk', 'public');
    }

    private function generatePath(int $tenantId, int $staffId): string
    {
        return sprintf('portfolios/%d/staff/%d', $tenantId, $staffId);
    }

    private function generateFileName(UploadedFile $file): string
    {
        return sprintf(
            '%s_%s.%s',
            now()->format('YmdHis'),
            Str::random(8),
            $file->getClientOriginalExtension()
        );
    }
}
