<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $service_id
 * @property int $booking_field_id
 * @property bool $is_enabled
 * @property bool $is_required
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Service $service
 * @property-read BookingField $bookingField
 */
final class ServiceBookingFieldOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_id',
        'booking_field_id',
        'is_enabled',
        'is_required',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * @return BelongsTo<BookingField, $this>
     */
    public function bookingField(): BelongsTo
    {
        return $this->belongsTo(BookingField::class, 'booking_field_id');
    }
}
