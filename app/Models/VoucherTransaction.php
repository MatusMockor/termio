<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\VoucherTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $voucher_id
 * @property int|null $appointment_id
 * @property VoucherTransactionType $type
 * @property string $amount
 * @property array<string, mixed>|null $metadata
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Voucher $voucher
 * @property-read Appointment|null $appointment
 * @property-read User|null $createdByUser
 */
final class VoucherTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_id',
        'appointment_id',
        'type',
        'amount',
        'metadata',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => VoucherTransactionType::class,
            'amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Voucher, $this>
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
