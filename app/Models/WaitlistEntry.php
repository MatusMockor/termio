<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WaitlistEntrySource;
use App\Enums\WaitlistEntryStatus;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $service_id
 * @property int|null $preferred_staff_id
 * @property Carbon|null $preferred_date
 * @property string|null $time_from
 * @property string|null $time_to
 * @property string $client_name
 * @property string $client_phone
 * @property string|null $client_email
 * @property string|null $notes
 * @property WaitlistEntryStatus $status
 * @property WaitlistEntrySource $source
 * @property int|null $converted_appointment_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Service $service
 * @property-read StaffProfile|null $preferredStaff
 * @property-read Appointment|null $convertedAppointment
 */
final class WaitlistEntry extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'service_id',
        'preferred_staff_id',
        'preferred_date',
        'time_from',
        'time_to',
        'client_name',
        'client_phone',
        'client_email',
        'notes',
        'status',
        'source',
        'converted_appointment_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_date' => 'date',
            'status' => WaitlistEntryStatus::class,
            'source' => WaitlistEntrySource::class,
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
     * @return BelongsTo<StaffProfile, $this>
     */
    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'preferred_staff_id');
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function convertedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'converted_appointment_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', WaitlistEntryStatus::Pending->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('preferred_date')->orderBy('created_at');
    }
}
