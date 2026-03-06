<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class ServiceBookingFieldConfigResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        return [
            'id' => $this['id'],
            'key' => $this['key'],
            'label' => $this['label'],
            'type' => $this['type'],
            'options' => $this['options'],
            'base' => [
                'is_required' => $this['base']['is_required'],
                'is_active' => $this['base']['is_active'],
                'sort_order' => $this['base']['sort_order'],
            ],
            'override' => $this['override'],
            'effective' => $this['effective'],
        ];
    }
}
