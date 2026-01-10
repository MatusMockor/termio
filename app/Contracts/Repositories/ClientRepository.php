<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface ClientRepository
{
    public function find(int $id): ?Client;

    public function findOrFail(int $id): Client;

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Client;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data): Client;

    public function delete(Client $client): void;

    /**
     * @return LengthAwarePaginator<int, Client>
     */
    public function paginate(?string $status = null, int $perPage = 25): LengthAwarePaginator;

    /**
     * @return Collection<int, Client>
     */
    public function search(string $term, int $limit = 20): Collection;
}
