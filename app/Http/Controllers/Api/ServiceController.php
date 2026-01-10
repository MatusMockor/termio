<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Service\ServiceCreateAction;
use App\Actions\Service\ServiceReorderAction;
use App\Actions\Service\ServiceUpdateAction;
use App\Contracts\Repositories\ServiceRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Service\ReorderServicesRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

final class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
    ) {}

    public function index(): JsonResponse
    {
        $services = $this->serviceRepository->getAllOrdered();

        return response()->json(['data' => $services]);
    }

    public function store(StoreServiceRequest $request, ServiceCreateAction $action): JsonResponse
    {
        $service = $action->handle($request->toDTO());

        return response()->json(['data' => $service], 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json(['data' => $service]);
    }

    public function update(
        UpdateServiceRequest $request,
        Service $service,
        ServiceUpdateAction $action
    ): JsonResponse {
        $service = $action->handle($service, $request->toDTO());

        return response()->json(['data' => $service]);
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
