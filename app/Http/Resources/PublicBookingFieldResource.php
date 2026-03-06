<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class PublicBookingFieldResource extends JsonResource
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
            'is_required' => $this['is_required'],
            'sort_order' => $this['sort_order'],
        ];
    }
}
