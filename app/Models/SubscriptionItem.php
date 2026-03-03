<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subscription_id
 * @property string $stripe_id
 * @property string $stripe_product
 * @property string $stripe_price
 * @property string|null $meter_id
 * @property int|null $quantity
 * @property string|null $meter_event_name
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Subscription $subscription
 */
final class SubscriptionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'stripe_id',
        'stripe_product',
        'stripe_price',
        'meter_id',
        'quantity',
        'meter_event_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meter_id' => 'string',
            'quantity' => 'integer',
            'meter_event_name' => 'string',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
