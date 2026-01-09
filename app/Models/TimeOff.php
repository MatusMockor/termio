<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $staff_id
 * @property Carbon $date
 * @property string|null $start_time
 * @property string|null $end_time
 * @property string|null $reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read StaffProfile|null $staff
 */
final class TimeOff extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'time_off';

    protected $fillable = [
        'tenant_id',
        'staff_id',
        'date',
        'start_time',
        'end_time',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<StaffProfile, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForStaff(Builder $query, ?int $staffId): Builder
    {
        return $query->where('staff_id', $staffId);
    }

    public function isAllDay(): bool
    {
        return $this->start_time === null && $this->end_time === null;
    }
}
