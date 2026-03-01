<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Billable;
use Laravel\Cashier\PaymentMethod as CashierPaymentMethod;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo
 * @property BusinessType|null $business_type
 * @property string|null $address
 * @property string|null $phone
 * @property string|null $country
 * @property string|null $vat_id
 * @property Carbon|null $vat_id_verified_at
 * @property string $timezone
 * @property int $reservation_lead_time_hours
 * @property int $reservation_max_days_in_advance
 * @property int $reservation_slot_interval_minutes
 * @property array<string, mixed> $settings
 * @property string $status
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $onboarding_completed_at
 * @property string|null $onboarding_step
 * @property array<string, mixed>|null $onboarding_data
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $owner
 * @property-read Collection<int, User> $users
 * @property-read Collection<int, Service> $services
 * @property-read Collection<int, Client> $clients
 * @property-read Collection<int, Appointment> $appointments
 * @property-read Collection<int, WorkingHours> $workingHours
 * @property-read Subscription|null $localSubscription
 * @property-read Collection<int, Invoice> $invoices
 * @property-read Collection<int, UsageRecord> $usageRecords
 */
final class Tenant extends Model
{
    use Billable;
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'business_type',
        'address',
        'phone',
        'country',
        'vat_id',
        'vat_id_verified_at',
        'timezone',
        'reservation_lead_time_hours',
        'reservation_max_days_in_advance',
        'reservation_slot_interval_minutes',
        'settings',
        'status',
        'stripe_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'onboarding_completed_at',
        'onboarding_step',
        'onboarding_data',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'vat_id_verified_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'onboarding_data' => 'array',
            'reservation_lead_time_hours' => 'integer',
            'reservation_max_days_in_advance' => 'integer',
            'reservation_slot_interval_minutes' => 'integer',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasOne<User, $this>
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->where('role', 'owner');
    }

    /**
     * @return HasMany<Service, $this>
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * @return HasMany<Client, $this>
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<WorkingHours, $this>
     */
    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHours::class);
    }

    /**
     * Get the local subscription record for this tenant.
     *
     * @return HasOne<Subscription, $this>
     */
    public function localSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<UsageRecord, $this>
     */
    public function usageRecords(): HasMany
    {
        return $this->hasMany(UsageRecord::class);
    }

    /**
     * Get the default payment method from Stripe.
     */
    public function getDefaultPaymentMethod(): ?CashierPaymentMethod
    {
        if (! $this->hasStripeId()) {
            return null;
        }

        return $this->defaultPaymentMethod();
    }

    /**
     * Get the active local subscription record for this tenant.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->localSubscription()
            ->where('stripe_status', 'active')
            ->first();
    }

    /**
     * Determine if the tenant is currently within their trial period.
     */
    public function isOnTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Get the number of days remaining in the trial period.
     */
    public function trialDaysRemaining(): int
    {
        if (! $this->isOnTrial()) {
            return 0;
        }

        return (int) now()->diffInDays($this->trial_ends_at, false);
    }

    /**
     * Determine if onboarding has been completed.
     */
    public function isOnboardingCompleted(): bool
    {
        return $this->onboarding_completed_at !== null;
    }

    /**
     * Mark onboarding as complete.
     */
    public function markOnboardingComplete(): void
    {
        $this->onboarding_completed_at = now();
        $this->onboarding_step = null;
        $this->onboarding_data = null;
        $this->save();
    }

    /**
     * Get onboarding progress data.
     *
     * @return array<string, mixed>
     */
    public function getOnboardingProgress(): array
    {
        return [
            'completed' => $this->isOnboardingCompleted(),
            'current_step' => $this->onboarding_step,
            'data' => $this->onboarding_data ?? [],
            'completed_at' => $this->onboarding_completed_at?->toIso8601String(),
        ];
    }

    public function getReservationLeadTimeHours(): int
    {
        $configuredValue = $this->getAttribute('reservation_lead_time_hours');

        if (is_int($configuredValue)) {
            return $configuredValue;
        }

        return (int) config('reservation.defaults.lead_time_hours');
    }

    public function getReservationMaxDaysInAdvance(): int
    {
        $configuredValue = $this->getAttribute('reservation_max_days_in_advance');

        if (is_int($configuredValue)) {
            return $configuredValue;
        }

        return (int) config('reservation.defaults.max_days_in_advance');
    }

    public function getReservationSlotIntervalMinutes(): int
    {
        $configuredValue = $this->getAttribute('reservation_slot_interval_minutes');

        if (is_int($configuredValue)) {
            return $configuredValue;
        }

        return (int) config('reservation.defaults.slot_interval_minutes');
    }

    /**
     * Get the public URL for the tenant's logo.
     */
    public function getLogoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        return Storage::disk(config('filesystems.logo_disk', 'public'))->url($this->logo);
    }
}
