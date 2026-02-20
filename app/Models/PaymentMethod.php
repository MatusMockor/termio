<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $stripe_payment_method_id
 * @property string $type
 * @property string|null $card_brand
 * @property string|null $card_last4
 * @property int|null $card_exp_month
 * @property int|null $card_exp_year
 * @property bool $is_default
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 */
final class PaymentMethod extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'stripe_payment_method_id',
        'type',
        'card_brand',
        'card_last4',
        'card_exp_month',
        'card_exp_year',
        'is_default',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'card_exp_month' => 'integer',
            'card_exp_year' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCards(Builder $query): Builder
    {
        return $query->where('type', 'card');
    }

    public function isExpired(): bool
    {
        if (! $this->hasCardExpiration()) {
            return false;
        }

        $expiry = Carbon::createFromDate($this->card_exp_year, $this->card_exp_month, 1)
            ->endOfMonth();

        return $expiry->isPast();
    }

    public function isExpiringSoon(): bool
    {
        if (! $this->hasCardExpiration()) {
            return false;
        }

        $expiry = Carbon::createFromDate($this->card_exp_year, $this->card_exp_month, 1)
            ->endOfMonth();

        return $expiry->isBetween(now(), now()->addMonths(2));
    }

    public function hasCardExpiration(): bool
    {
        return (bool) ($this->card_exp_month && $this->card_exp_year);
    }
}
