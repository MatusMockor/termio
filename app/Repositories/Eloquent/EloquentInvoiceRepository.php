<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Contracts\Repositories\InvoiceRepository;
use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentInvoiceRepository implements InvoiceRepository
{
    public function create(array $data): Invoice
    {
        return Invoice::create($data);
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);

        return $invoice->fresh() ?? $invoice;
    }

    public function findById(int $id): ?Invoice
    {
        return Invoice::find($id);
    }

    public function findByStripeId(string $stripeId): ?Invoice
    {
        return Invoice::where('stripe_invoice_id', $stripeId)->first();
    }

    public function findByNumber(string $number): ?Invoice
    {
        return Invoice::where('invoice_number', $number)->first();
    }

    public function getByTenant(Tenant $tenant, int $limit = 50): Collection
    {
        return Invoice::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function getNextInvoiceNumber(): string
    {
        return DB::transaction(static function (): string {
            $yearMonth = now()->format('Y-m');
            $prefix = 'INV-'.$yearMonth.'-';

            $lastInvoice = Invoice::where('invoice_number', 'like', $prefix.'%')
                ->orderByDesc('invoice_number')
                ->lockForUpdate()
                ->first();

            $nextNumber = 1;

            if ($lastInvoice !== null) {
                $lastNumber = (int) mb_substr($lastInvoice->invoice_number, -4);
                $nextNumber = $lastNumber + 1;
            }

            return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
        });
    }
}
