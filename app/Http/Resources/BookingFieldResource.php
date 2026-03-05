<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\BookingField;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BookingField
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class BookingFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type->value,
            'options' => $this->options,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
