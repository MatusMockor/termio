<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Portfolio\PortfolioTagCreateAction;
use App\Actions\Portfolio\PortfolioTagDeleteAction;
use App\Actions\Portfolio\PortfolioTagUpdateAction;
use App\Contracts\Repositories\PortfolioTagRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\StorePortfolioTagRequest;
use App\Http\Requests\Portfolio\UpdatePortfolioTagRequest;
use App\Http\Resources\PortfolioTagResource;
use App\Models\PortfolioTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PortfolioTagController extends Controller
{
    public function __construct(
        private readonly PortfolioTagRepository $repository,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $tags = $this->repository->getAll();

        return PortfolioTagResource::collection($tags);
    }

    public function store(StorePortfolioTagRequest $request, PortfolioTagCreateAction $action): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $tag = $action->handle($request->toDTO(), $tenantId);

        return PortfolioTagResource::make($tag)
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdatePortfolioTagRequest $request,
        PortfolioTag $portfolioTag,
        PortfolioTagUpdateAction $action
    ): PortfolioTagResource {
        $tag = $action->handle($portfolioTag, $request->toDTO());

        return new PortfolioTagResource($tag);
    }

    public function destroy(PortfolioTag $portfolioTag, PortfolioTagDeleteAction $action): JsonResponse
    {
        $action->handle($portfolioTag);

        return response()->json(null, 204);
    }
}
