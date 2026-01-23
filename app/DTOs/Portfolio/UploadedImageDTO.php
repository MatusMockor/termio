<?php

declare(strict_types=1);

namespace App\DTOs\Portfolio;

final readonly class UploadedImageDTO
{
    public function __construct(
        public string $filePath,
        public string $fileName,
        public int $fileSize,
        public string $mimeType,
        public string $disk,
    ) {}
}
