<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\ClientTag\StoreClientTagRequest;
use App\Http\Requests\ClientTag\UpdateClientTagRequest;
use App\Http\Resources\ClientTagResource;
use App\Models\ClientTag;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ClientTagController extends ApiController
{
    public function index(): AnonymousResourceCollection
    {
        return ClientTagResource::collection(ClientTag::ordered()->get());
    }

    public function store(StoreClientTagRequest $request): JsonResponse
    {
        $tenant = $this->resolveTenantOrFail($request);

        $tag = ClientTag::create([
            'tenant_id' => $tenant->id,
            'name' => $request->getName(),
            'color' => $request->getColor(),
            'sort_order' => $request->getSortOrder(),
        ]);

        return ClientTagResource::make($tag)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateClientTagRequest $request, ClientTag $tag): ClientTagResource
    {
        $this->ensureTenantOwnership($request, $tag->tenant_id);

        $tag->update($request->getUpdateData());

        return new ClientTagResource($tag->refresh());
    }

    public function destroy(Request $request, ClientTag $tag): JsonResponse
    {
        $this->ensureTenantOwnership($request, $tag->tenant_id);

        $tag->delete();

        return response()->json(null, 204);
    }

    private function resolveTenantOrFail(Request $request): Tenant
    {
        $tenantId = $request->user()?->tenant_id;

        return Tenant::where('id', $tenantId)->firstOrFail();
    }

    private function ensureTenantOwnership(Request $request, int $resourceTenantId): void
    {
        $tenantId = $request->user()?->tenant_id;

        if (! is_int($tenantId) || $tenantId !== $resourceTenantId) {
            abort(404);
        }
    }
}
