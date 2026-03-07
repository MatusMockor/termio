<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\ClientRepository;
use App\Enums\ClientBookingState;
use App\Enums\ClientRiskLevel;
use App\Enums\ClientStatus;
use App\Models\Client;
use App\Services\Client\ClientRiskLevelResolver;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class EloquentClientRepository implements ClientRepository
{
    public function __construct(
        private readonly ClientRiskLevelResolver $riskLevelResolver,
    ) {}

    public function find(int $id): ?Client
    {
        return Client::find($id);
    }

    public function findOrFail(int $id): Client
    {
        return Client::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Client
    {
        return Client::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data): Client
    {
        $client->update($data);

        return $client;
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    public function paginate(
        ?ClientStatus $status,
        ?ClientBookingState $bookingState,
        ?ClientRiskLevel $riskLevel,
        array $tagIds,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Client::with('tags');

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        if ($bookingState !== null) {
            $query->withBookingState($bookingState);
        }

        if ($riskLevel !== null) {
            $this->riskLevelResolver->applyFilter($query, $riskLevel);
        }

        $query->taggedWith($tagIds);

        return $query->orderBy('name')->paginate($perPage);
    }

    /**
     * @return Collection<int, Client>
     */
    public function search(string $term, int $limit = 20): Collection
    {
        return Client::with('tags')->search($term)->limit($limit)->get();
    }
}
