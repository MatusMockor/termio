<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Client\ClientCreateAction;
use App\Actions\Client\ClientUpdateAction;
use App\Contracts\Repositories\ClientRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\IndexClientsRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ClientController extends Controller
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
    ) {}

    public function index(IndexClientsRequest $request): AnonymousResourceCollection
    {
        $clients = $this->clientRepository->paginate(
            status: $request->getStatus(),
            perPage: $request->getPerPage(),
        );

        return ClientResource::collection($clients);
    }

    public function store(StoreClientRequest $request, ClientCreateAction $action): JsonResponse
    {
        $client = $action->handle($request->toDTO());

        return response()->json(['data' => $client], 201);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load(['appointments' => static function (HasMany $query): void {
            $query->with(['service'])->orderBy('starts_at', 'desc')->limit(10);
        }]);

        return response()->json(['data' => $client]);
    }

    public function update(UpdateClientRequest $request, Client $client, ClientUpdateAction $action): JsonResponse
    {
        $client = $action->handle($client, $request->toDTO());

        return response()->json(['data' => $client]);
    }

    public function destroy(Client $client): JsonResponse
    {
        $this->clientRepository->delete($client);

        return response()->json(null, 204);
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->input('q', '');

        if (strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $clients = $this->clientRepository->search($term);

        return response()->json(['data' => $clients]);
    }
}
