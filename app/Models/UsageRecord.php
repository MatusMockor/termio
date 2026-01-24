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
 * @property string $period
 * @property int $reservations_count
 * @property int $reservations_limit
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 */
final class UsageRecord extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'period',
        'reservations_count',
        'reservations_limit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reservations_count' => 'integer',
            'reservations_limit' => 'integer',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCurrentMonth(Builder $query): Builder
    {
        return $query->where('period', now()->format('Y-m'));
    }

    public function isUnlimited(): bool
    {
        return $this->reservations_limit === -1;
    }

    public function hasReachedLimit(): bool
    {
        if ($this->isUnlimited()) {
            return false;
        }

        return $this->reservations_count >= $this->reservations_limit;
    }

    public function getRemainingReservations(): int
    {
        if ($this->isUnlimited()) {
            return -1;
        }

        return max(0, $this->reservations_limit - $this->reservations_count);
    }

    public function getUsagePercentage(): float
    {
        if ($this->isUnlimited()) {
            return 0.0;
        }

        if ($this->reservations_limit === 0) {
            return 100.0;
        }

        return ($this->reservations_count / $this->reservations_limit) * 100;
    }
}
