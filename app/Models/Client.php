<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $notes
 * @property int $total_visits
 * @property string $total_spent
 * @property Carbon|null $last_visit_at
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Tenant $tenant
 * @property-read Collection<int, Appointment> $appointments
 */
final class Client extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'email',
        'notes',
        'total_visits',
        'total_spent',
        'last_visit_at',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_visits' => 'integer',
            'total_spent' => 'decimal:2',
            'last_visit_at' => 'datetime',
        ];
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
        return $query->where('status', 'active');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVip(Builder $query): Builder
    {
        return $query->where('status', 'vip');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $loweredTerm = mb_strtolower($term);

        return $query->where(static function (Builder $q) use ($loweredTerm): void {
            $q->whereRaw('LOWER(name) LIKE ?', ["%{$loweredTerm}%"])
                ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$loweredTerm}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$loweredTerm}%"]);
        });
    }

    public function incrementVisit(float $amount): void
    {
        $this->increment('total_visits');
        $this->increment('total_spent', $amount);
        $this->update(['last_visit_at' => now()]);
    }
}
