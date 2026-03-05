<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $category_id
 * @property string $name
 * @property string|null $description
 * @property int $duration_minutes
 * @property string $price
 * @property string|null $category
 * @property int $priority
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_bookable_online
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read ServiceCategory|null $categoryRelation
 * @property-read Collection<int, Appointment> $appointments
 * @property-read Collection<int, ServiceBookingFieldOverride> $bookingFieldOverrides
 */
final class Service extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'description',
        'duration_minutes',
        'price',
        'category',
        'priority',
        'sort_order',
        'is_active',
        'is_bookable_online',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'duration_minutes' => 'integer',
            'price' => 'decimal:2',
            'priority' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_bookable_online' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<ServiceBookingFieldOverride, $this>
     */
    public function bookingFieldOverrides(): HasMany
    {
        return $this->hasMany(ServiceBookingFieldOverride::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('services.is_active', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBookableOnline(Builder $query): Builder
    {
        return $query->where('services.is_bookable_online', true);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderByDesc('priority')
            ->orderBy('sort_order')
            ->orderBy('name');
    }
}
