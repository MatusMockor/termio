<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ClientBookingState;
use App\Enums\ClientRiskLevel;
use App\Enums\ClientStatus;
use App\Models\Traits\BelongsToTenant;
use App\Services\Client\ClientRiskLevelResolver;
use App\Support\ClientIdentityNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $phone_normalized
 * @property string|null $email
 * @property string|null $email_normalized
 * @property string|null $notes
 * @property bool $is_blacklisted
 * @property bool $is_whitelisted
 * @property string|null $booking_control_note
 * @property int $no_show_count
 * @property int $late_cancellation_count
 * @property Carbon|null $last_no_show_at
 * @property Carbon|null $last_late_cancellation_at
 * @property int $total_visits
 * @property string $total_spent
 * @property Carbon|null $last_visit_at
 * @property string $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ClientRiskLevel $risk_level
 * @property-read Tenant $tenant
 * @property-read Collection<int, Appointment> $appointments
 * @property-read Collection<int, ClientTag> $tags
 */
final class Client extends Model
{
    use BelongsToTenant;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'phone',
        'phone_normalized',
        'email',
        'email_normalized',
        'notes',
        'is_blacklisted',
        'is_whitelisted',
        'booking_control_note',
        'no_show_count',
        'late_cancellation_count',
        'last_no_show_at',
        'last_late_cancellation_at',
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
            'is_blacklisted' => 'boolean',
            'is_whitelisted' => 'boolean',
            'no_show_count' => 'integer',
            'late_cancellation_count' => 'integer',
            'last_no_show_at' => 'datetime',
            'last_late_cancellation_at' => 'datetime',
            'total_visits' => 'integer',
            'total_spent' => 'decimal:2',
            'last_visit_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::saving(static function (Client $client): void {
            $client->phone_normalized = ClientIdentityNormalizer::normalizePhone($client->phone);
            $client->email_normalized = ClientIdentityNormalizer::normalizeEmail($client->email);
        });
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return BelongsToMany<ClientTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ClientTag::class, 'client_tag_assignments')
            ->withTimestamps()
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ClientStatus::Active->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVip(Builder $query): Builder
    {
        return $query->where('status', ClientStatus::Vip->value);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $loweredTerm = mb_strtolower($term);
        $normalizedPhone = ClientIdentityNormalizer::normalizePhone($term);
        $normalizedEmail = ClientIdentityNormalizer::normalizeEmail($term);

        return $query->where(static function (Builder $q) use ($loweredTerm): void {
            $q->whereRaw('LOWER(name) LIKE ?', ["%{$loweredTerm}%"])
                ->orWhereRaw('LOWER(phone) LIKE ?', ["%{$loweredTerm}%"])
                ->orWhereRaw('LOWER(email) LIKE ?', ["%{$loweredTerm}%"]);
        })->when(
            $normalizedPhone !== null || $normalizedEmail !== null,
            static function (Builder $q) use ($normalizedPhone, $normalizedEmail): void {
                if ($normalizedPhone !== null) {
                    $q->orWhere('phone_normalized', 'LIKE', "%{$normalizedPhone}%");
                }

                if ($normalizedEmail !== null) {
                    $q->orWhere('email_normalized', 'LIKE', "%{$normalizedEmail}%");
                }
            },
        );
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWithBookingState(Builder $query, ClientBookingState $bookingState): Builder
    {
        return match ($bookingState) {
            ClientBookingState::Normal => $query
                ->where('is_blacklisted', false)
                ->where('is_whitelisted', false),
            ClientBookingState::Blacklisted => $query->where('is_blacklisted', true),
            ClientBookingState::Whitelisted => $query->where('is_whitelisted', true),
        };
    }

    /**
     * @param  Builder<static>  $query
     * @param  array<int, int>  $tagIds
     * @return Builder<static>
     */
    public function scopeTaggedWith(Builder $query, array $tagIds): Builder
    {
        if ($tagIds === []) {
            return $query;
        }

        return $query->whereHas('tags', static fn (Builder $builder): Builder => $builder->whereIn('client_tags.id', $tagIds));
    }

    public function incrementVisit(float $amount): void
    {
        $this->increment('total_visits');
        $this->increment('total_spent', $amount);
        $this->update(['last_visit_at' => now()]);
    }

    public function canBookOnline(): bool
    {
        return ! $this->is_blacklisted;
    }

    public function getRiskLevelAttribute(): ClientRiskLevel
    {
        return app(ClientRiskLevelResolver::class)->resolve($this);
    }
}
