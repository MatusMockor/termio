<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\States\SubscriptionState;
use App\Enums\SubscriptionStatus;
use App\Services\Subscription\SubscriptionStateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $plan_id
 * @property string $type
 * @property string $stripe_id
 * @property SubscriptionStatus $stripe_status
 * @property string|null $stripe_price
 * @property string $billing_cycle
 * @property int $quantity
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $ends_at
 * @property int|null $scheduled_plan_id
 * @property Carbon|null $scheduled_change_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Plan $plan
 * @property-read Plan|null $scheduledPlan
 * @property-read Collection<int, SubscriptionItem> $items
 * @property-read Collection<int, Invoice> $invoices
 */
final class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'type',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'billing_cycle',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'scheduled_plan_id',
        'scheduled_change_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stripe_status' => SubscriptionStatus::class,
            'quantity' => 'integer',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'scheduled_change_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function scheduledPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'scheduled_plan_id');
    }

    /**
     * @return HasMany<SubscriptionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('stripe_status', SubscriptionStatus::Active);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOnTrial(Builder $query): Builder
    {
        return $query->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now());
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeCanceled(Builder $query): Builder
    {
        return $query->whereNotNull('ends_at');
    }

    public function isActive(): bool
    {
        return $this->stripe_status === SubscriptionStatus::Active;
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function canceled(): bool
    {
        return $this->ends_at !== null;
    }

    public function onGracePeriod(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isFuture();
    }

    public function ended(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function hasScheduledPlanChange(): bool
    {
        return $this->scheduled_plan_id !== null;
    }

    /**
     * Get the current state object for this subscription.
     *
     * Uses the State pattern to encapsulate state-specific behavior.
     */
    public function getState(): SubscriptionState
    {
        return app(SubscriptionStateFactory::class)->create($this);
    }

    /**
     * Check if subscription can be upgraded in current state.
     *
     * Delegates to state object for state-specific logic.
     */
    public function canUpgrade(): bool
    {
        return $this->getState()->canUpgrade();
    }

    /**
     * Check if subscription can be downgraded in current state.
     *
     * Delegates to state object for state-specific logic.
     */
    public function canDowngrade(): bool
    {
        return $this->getState()->canDowngrade();
    }

    /**
     * Check if subscription can be canceled in current state.
     *
     * Delegates to state object for state-specific logic.
     */
    public function canCancel(): bool
    {
        return $this->getState()->canCancel();
    }

    /**
     * Check if subscription can be resumed in current state.
     *
     * Delegates to state object for state-specific logic.
     */
    public function canResume(): bool
    {
        return $this->getState()->canResume();
    }

    /**
     * Get list of available actions in current state.
     *
     * @return array<int, string>
     */
    public function getAvailableActions(): array
    {
        return $this->getState()->getAllowedActions();
    }
}
