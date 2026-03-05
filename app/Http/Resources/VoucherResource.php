<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Voucher
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'initial_amount' => (float) $this->initial_amount,
            'balance_amount' => (float) $this->balance_amount,
            'currency' => $this->currency,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'status' => $this->status->value,
            'issued_to_name' => $this->issued_to_name,
            'issued_to_email' => $this->issued_to_email,
            'note' => $this->note,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
