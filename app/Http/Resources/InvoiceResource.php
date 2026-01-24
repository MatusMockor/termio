<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invoice
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'amount_net' => (float) $this->amount_net,
            'vat_rate' => (float) $this->vat_rate,
            'vat_amount' => (float) $this->vat_amount,
            'amount_gross' => (float) $this->amount_gross,
            'currency' => $this->currency,
            'status' => $this->status,
            'customer_name' => $this->customer_name,
            'customer_address' => $this->customer_address,
            'customer_country' => $this->customer_country,
            'customer_vat_id' => $this->customer_vat_id,
            'line_items' => $this->line_items,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'billing_period_start' => $this->billing_period_start?->toDateString(),
            'billing_period_end' => $this->billing_period_end?->toDateString(),
            'has_pdf' => $this->pdf_path !== null,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
