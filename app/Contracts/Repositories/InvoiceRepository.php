<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Collection;

interface InvoiceRepository
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Invoice;

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice;

    public function findById(int $id): ?Invoice;

    public function findByStripeId(string $stripeId): ?Invoice;

    public function findByNumber(string $number): ?Invoice;

    /**
     * @return Collection<int, Invoice>
     */
    public function getByTenant(Tenant $tenant, int $limit = 50): Collection;

    /**
     * Generate the next sequential invoice number with locking.
     */
    public function getNextInvoiceNumber(): string;
}
