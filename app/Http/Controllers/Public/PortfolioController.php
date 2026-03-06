<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Contracts\Repositories\PortfolioImageRepository;
use App\Contracts\Repositories\PortfolioTagRepository;
use App\Http\Controllers\Controller;
use App\Http\Resources\PortfolioImageResource;
use App\Http\Resources\PortfolioTagResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PortfolioController extends Controller
{
    public function __construct(
        private readonly PortfolioImageRepository $imageRepository,
        private readonly PortfolioTagRepository $tagRepository,
    ) {}

    public function gallery(Request $request, string $tenantSlug): AnonymousResourceCollection
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $tagIds = $request->has('tags')
            ? array_map('intval', explode(',', $request->input('tags')))
            : [];

        $images = $tagIds !== []
            ? $this->imageRepository->getPublicByTags($tenant->id, $tagIds)
            : $this->imageRepository->getPublicByTenant($tenant->id);

        return PortfolioImageResource::collection($images);
    }

    public function staffGallery(string $tenantSlug, int $staffId): AnonymousResourceCollection
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();
        $images = $this->imageRepository->getPublicByStaff($tenant->id, $staffId);

        return PortfolioImageResource::collection($images);
    }

    public function tags(string $tenantSlug): AnonymousResourceCollection
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();
        $tags = $this->tagRepository->getByTenantWithoutScope($tenant->id);

        return PortfolioTagResource::collection($tags);
    }
}
