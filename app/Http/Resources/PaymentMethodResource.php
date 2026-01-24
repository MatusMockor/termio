<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PaymentMethod
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class PaymentMethodResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'card_brand' => $this->card_brand,
            'card_last4' => $this->card_last4,
            'card_exp_month' => $this->card_exp_month,
            'card_exp_year' => $this->card_exp_year,
            'is_default' => $this->is_default,
            'is_expired' => $this->isExpired(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
