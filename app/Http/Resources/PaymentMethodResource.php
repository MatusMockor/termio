<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Billing\PaymentMethodDTO;
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
        if ($this->resource instanceof PaymentMethodDTO) {
            return $this->resource->toArray();
        }

        if (! $this->resource instanceof PaymentMethod) {
            return [];
        }

        return PaymentMethodDTO::fromModel($this->resource)->toArray();
    }
}
