<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClientController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Client::query();

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $clients = $query->orderBy('name')->paginate(25);

        return response()->json($clients);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create([
            'name' => $request->getName(),
            'phone' => $request->getPhone(),
            'email' => $request->getEmail(),
            'notes' => $request->getNotes(),
            'status' => $request->getStatus(),
        ]);

        return response()->json(['data' => $client], 201);
    }

    public function show(Client $client): JsonResponse
    {
        $client->load(['appointments' => static function (HasMany $query): void {
            $query->with(['service'])->orderBy('starts_at', 'desc')->limit(10);
        }]);

        return response()->json(['data' => $client]);
    }

    public function update(UpdateClientRequest $request, Client $client): JsonResponse
    {
        $data = array_filter([
            'name' => $request->getName(),
            'phone' => $request->getPhone(),
            'email' => $request->getEmail(),
            'notes' => $request->getNotes(),
            'status' => $request->getStatus(),
        ], static fn (mixed $value): bool => $value !== null);

        $client->update($data);

        return response()->json(['data' => $client]);
    }

    public function destroy(Client $client): JsonResponse
    {
        $client->delete();

        return response()->json(null, 204);
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->input('q', '');

        if (strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $clients = Client::search($term)->limit(20)->get();

        return response()->json(['data' => $clients]);
    }
}
