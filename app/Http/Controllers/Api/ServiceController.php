<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Service\IndexServicesAction;
use App\Actions\Service\ServiceCreateAction;
use App\Actions\Service\ServiceReorderAction;
use App\Actions\Service\ServiceUpdateAction;
use App\Contracts\Repositories\ServiceRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\IndexServicesRequest;
use App\Http\Requests\Service\ReorderServicesRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
    ) {}

    public function index(IndexServicesRequest $request, IndexServicesAction $action): AnonymousResourceCollection
    {
        $services = $action->handle($request->toDTO());

        return ServiceResource::collection($services);
    }

    public function store(StoreServiceRequest $request, ServiceCreateAction $action): JsonResponse
    {
        $service = $action->handle($request->toDTO());
        $service->load('categoryRelation');

        return ServiceResource::make($service)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Service $service): ServiceResource
    {
        return new ServiceResource($service->load('categoryRelation'));
    }

    public function update(
        UpdateServiceRequest $request,
        Service $service,
        ServiceUpdateAction $action
    ): ServiceResource {
        $service = $action->handle($service, $request->toDTO());

        return new ServiceResource($service->load('categoryRelation'));
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->serviceRepository->delete($service);

        return response()->json(null, 204);
    }

    public function reorder(ReorderServicesRequest $request, ServiceReorderAction $action): JsonResponse
    {
        $action->handle($request->getOrder());

        return response()->json(['message' => 'Services reordered successfully.']);
    }
}
