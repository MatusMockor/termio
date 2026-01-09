<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\ReorderServicesRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

final class ServiceController extends Controller
{
    public function index(): JsonResponse
    {
        $services = Service::ordered()->get();

        return response()->json(['data' => $services]);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $maxOrder = Service::max('sort_order') ?? 0;

        $service = Service::create([
            'name' => $request->getName(),
            'description' => $request->getDescription(),
            'duration_minutes' => $request->getDurationMinutes(),
            'price' => $request->getPrice(),
            'category' => $request->getCategory(),
            'is_active' => $request->isActive(),
            'is_bookable_online' => $request->isBookableOnline(),
            'sort_order' => $maxOrder + 1,
        ]);

        return response()->json(['data' => $service], 201);
    }

    public function show(Service $service): JsonResponse
    {
        return response()->json(['data' => $service]);
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $data = array_filter([
            'name' => $request->getName(),
            'description' => $request->getDescription(),
            'duration_minutes' => $request->getDurationMinutes(),
            'price' => $request->getPrice(),
            'category' => $request->getCategory(),
            'is_active' => $request->isActive(),
            'is_bookable_online' => $request->isBookableOnline(),
        ], static fn (mixed $value): bool => $value !== null);

        $service->update($data);

        return response()->json(['data' => $service]);
    }

    public function destroy(Service $service): JsonResponse
    {
        $service->delete();

        return response()->json(null, 204);
    }

    public function reorder(ReorderServicesRequest $request): JsonResponse
    {
        foreach ($request->getOrder() as $position => $serviceId) {
            Service::where('id', $serviceId)->update(['sort_order' => $position]);
        }

        return response()->json(['message' => 'Services reordered successfully.']);
    }
}
