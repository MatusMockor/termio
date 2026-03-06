<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Portfolio\PortfolioImageCreateAction;
use App\Actions\Portfolio\PortfolioImageDeleteAction;
use App\Actions\Portfolio\PortfolioImageReorderAction;
use App\Actions\Portfolio\PortfolioImageUpdateAction;
use App\Contracts\Repositories\PortfolioImageRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\ReorderPortfolioImagesRequest;
use App\Http\Requests\Portfolio\StorePortfolioImageRequest;
use App\Http\Requests\Portfolio\UpdatePortfolioImageRequest;
use App\Http\Resources\PortfolioImageResource;
use App\Models\PortfolioImage;
use App\Models\StaffProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PortfolioImageController extends Controller
{
    public function __construct(
        private readonly PortfolioImageRepository $repository,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $images = $this->repository->getAllOrdered();

        return PortfolioImageResource::collection($images);
    }

    public function store(StorePortfolioImageRequest $request, PortfolioImageCreateAction $action): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $image = $action->handle($request->toDTO(), $tenantId);

        return PortfolioImageResource::make($image)
            ->response()
            ->setStatusCode(201);
    }

    public function show(PortfolioImage $portfolioImage): PortfolioImageResource
    {
        $portfolioImage->load(['tags', 'staff']);

        return new PortfolioImageResource($portfolioImage);
    }

    public function update(
        UpdatePortfolioImageRequest $request,
        PortfolioImage $portfolioImage,
        PortfolioImageUpdateAction $action
    ): PortfolioImageResource {
        $image = $action->handle($portfolioImage, $request->toDTO());

        return new PortfolioImageResource($image);
    }

    public function destroy(PortfolioImage $portfolioImage, PortfolioImageDeleteAction $action): JsonResponse
    {
        $action->handle($portfolioImage);

        return response()->json(null, 204);
    }

    public function reorder(ReorderPortfolioImagesRequest $request, PortfolioImageReorderAction $action): JsonResponse
    {
        $action->handle($request->getOrder());

        return response()->json(['message' => 'Images reordered successfully.']);
    }

    public function byStaff(StaffProfile $staff): AnonymousResourceCollection
    {
        $images = $this->repository->getByStaffOrdered($staff->id);

        return PortfolioImageResource::collection($images);
    }
}
