<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VoucherStatus;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $initial_amount
 * @property string $balance_amount
 * @property string $currency
 * @property Carbon|null $expires_at
 * @property VoucherStatus $status
 * @property string|null $issued_to_name
 * @property string|null $issued_to_email
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, VoucherTransaction> $transactions
 * @property-read Collection<int, Appointment> $appointments
 */
final class Voucher extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'code',
        'initial_amount',
        'balance_amount',
        'currency',
        'expires_at',
        'status',
        'issued_to_name',
        'issued_to_email',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initial_amount' => 'decimal:2',
            'balance_amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'status' => VoucherStatus::class,
        ];
    }

    /**
     * @return HasMany<VoucherTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(VoucherTransaction::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VoucherStatus::Active->value);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isRedeemable(): bool
    {
        if ($this->status !== VoucherStatus::Active) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        return (float) $this->balance_amount > 0.0;
    }
}
