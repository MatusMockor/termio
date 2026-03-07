<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Client\ClientCreateAction;
use App\Actions\Client\ClientUpdateAction;
use App\Actions\Client\IndexClientsAction;
use App\Contracts\Repositories\ClientRepository;
use App\Http\Requests\Client\IndexClientsRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\SyncClientTagsRequest;
use App\Http\Requests\Client\UpdateClientBookingControlsRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ClientController extends ApiController
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
    ) {}

    public function index(IndexClientsRequest $request, IndexClientsAction $action): AnonymousResourceCollection
    {
        $clients = $action->handle($request->toDTO());

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request, ClientCreateAction $action): JsonResponse
    {
        $client = $action->handle($request->toDTO());

        return ClientResource::make($client->load('tags'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, Client $client): ClientResource
    {
        $this->ensureTenantOwnership($request, $client->tenant_id);

        $client->load(['tags', 'appointments' => static function (HasMany $query): void {
            $query->with(['service'])->orderBy('starts_at', 'desc')->limit(10);
        }]);

        return new ClientResource($client);
    }

    public function update(UpdateClientRequest $request, Client $client, ClientUpdateAction $action): ClientResource
    {
        $this->ensureTenantOwnership($request, $client->tenant_id);

        $client = $action->handle($client, $request->toDTO());

        return new ClientResource($client->load('tags'));
    }

    public function destroy(Request $request, Client $client): JsonResponse
    {
        $this->ensureTenantOwnership($request, $client->tenant_id);

        $this->clientRepository->delete($client);

        return response()->json(null, 204);
    }

    public function search(Request $request): JsonResponse|AnonymousResourceCollection
    {
        $term = $request->input('q', '');

        if (strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $clients = $this->clientRepository->search($term);

        return ClientResource::collection($clients);
    }

    public function syncTags(SyncClientTagsRequest $request, Client $client): ClientResource
    {
        $this->ensureTenantOwnership($request, $client->tenant_id);

        $client->tags()->sync($request->getTagIds());

        return new ClientResource($client->load('tags'));
    }

    public function updateBookingControls(UpdateClientBookingControlsRequest $request, Client $client): ClientResource
    {
        $this->ensureTenantOwnership($request, $client->tenant_id);

        $client->update([
            'is_blacklisted' => $request->isBlacklisted(),
            'is_whitelisted' => $request->isWhitelisted(),
            'booking_control_note' => $request->getBookingControlNote(),
        ]);

        return new ClientResource($client->fresh()->load('tags'));
    }

    private function ensureTenantOwnership(Request $request, int $resourceTenantId): void
    {
        $tenantId = $request->user()?->tenant_id;

        if (! is_int($tenantId) || $tenantId !== $resourceTenantId) {
            abort(404);
        }
    }
}
