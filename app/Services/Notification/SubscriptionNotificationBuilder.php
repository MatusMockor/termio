<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\Plan;
use App\Models\Tenant;
use App\Notifications\SubscriptionCanceledNotification;
use App\Notifications\SubscriptionDowngradedNotification;
use App\Notifications\SubscriptionDowngradeScheduledNotification;
use App\Notifications\SubscriptionUpgradedNotification;
use App\Notifications\TrialEndedNotification;
use Carbon\Carbon;
use RuntimeException;

/**
 * Builder for constructing subscription-related notifications with a fluent interface.
 *
 * This builder simplifies the creation of complex notification objects by providing
 * a clear, readable API for setting required and optional parameters.
 */
final class SubscriptionNotificationBuilder
{
    private ?Tenant $tenant = null;

    private ?Plan $plan = null;

    private ?Plan $previousPlan = null;

    private ?Carbon $effectiveAt = null;

    private ?string $reason = null;

    /**
     * @var array<string, mixed>
     */
    private array $metadata = [];

    /**
     * @var array<int, string>
     */
    private array $channels = ['mail'];

    /**
     * Set the tenant for the notification.
     */
    public function forTenant(Tenant $tenant): self
    {
        $this->tenant = $tenant;

        return $this;
    }

    /**
     * Set the plan for the notification (target plan for upgrades/downgrades).
     */
    public function withPlan(Plan $plan): self
    {
        $this->plan = $plan;

        return $this;
    }

    /**
     * Set the previous plan for upgrade/downgrade notifications.
     */
    public function withPreviousPlan(Plan $previousPlan): self
    {
        $this->previousPlan = $previousPlan;

        return $this;
    }

    /**
     * Set the effective date for scheduled changes.
     */
    public function effectiveAt(Carbon $date): self
    {
        $this->effectiveAt = $date;

        return $this;
    }

    /**
     * Set an optional reason for the notification.
     */
    public function withReason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Set optional metadata for the notification.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Set the notification channels.
     *
     * @param  array<int, string>  $channels
     */
    public function viaChannels(array $channels): self
    {
        $this->channels = $channels;

        return $this;
    }

    /**
     * Configure notification to send via mail only.
     */
    public function viaMail(): self
    {
        $this->channels = ['mail'];

        return $this;
    }

    /**
     * Build a downgrade scheduled notification.
     *
     * @throws RuntimeException when required parameters are missing
     */
    public function buildDowngradeScheduled(): SubscriptionDowngradeScheduledNotification
    {
        $this->validateTenant();
        $this->validatePlan('Target plan');
        $this->validatePreviousPlan();
        $this->validateEffectiveAt();

        /** @var Tenant $tenant */
        $tenant = $this->tenant;
        /** @var Plan $previousPlan */
        $previousPlan = $this->previousPlan;
        /** @var Plan $plan */
        $plan = $this->plan;
        /** @var Carbon $effectiveAt */
        $effectiveAt = $this->effectiveAt;

        return new SubscriptionDowngradeScheduledNotification(
            $tenant,
            $previousPlan,
            $plan,
            $effectiveAt,
        );
    }

    /**
     * Build an upgraded notification.
     *
     * @throws RuntimeException when required parameters are missing
     */
    public function buildUpgraded(): SubscriptionUpgradedNotification
    {
        $this->validateTenant();
        $this->validatePlan('New plan');
        $this->validatePreviousPlan();

        /** @var Tenant $tenant */
        $tenant = $this->tenant;
        /** @var Plan $previousPlan */
        $previousPlan = $this->previousPlan;
        /** @var Plan $plan */
        $plan = $this->plan;

        return new SubscriptionUpgradedNotification(
            $tenant,
            $previousPlan,
            $plan,
        );
    }

    /**
     * Build a canceled notification.
     *
     * @throws RuntimeException when required parameters are missing
     */
    public function buildCanceled(): SubscriptionCanceledNotification
    {
        $this->validateTenant();
        $this->validateEffectiveAt();

        /** @var Tenant $tenant */
        $tenant = $this->tenant;
        /** @var Carbon $effectiveAt */
        $effectiveAt = $this->effectiveAt;

        return new SubscriptionCanceledNotification(
            $tenant,
            $effectiveAt,
        );
    }

    /**
     * Build a trial ended notification.
     *
     * @throws RuntimeException when required parameters are missing
     */
    public function buildTrialEnded(bool $converted): TrialEndedNotification
    {
        $this->validateTenant();

        /** @var Tenant $tenant */
        $tenant = $this->tenant;

        return new TrialEndedNotification(
            $tenant,
            $converted,
        );
    }

    /**
     * Build a downgraded notification.
     *
     * @throws RuntimeException when required parameters are missing
     */
    public function buildDowngraded(): SubscriptionDowngradedNotification
    {
        $this->validateTenant();
        $this->validatePlan('Plan');

        /** @var Tenant $tenant */
        $tenant = $this->tenant;
        /** @var Plan $plan */
        $plan = $this->plan;

        return new SubscriptionDowngradedNotification(
            $tenant,
            $plan,
        );
    }

    /**
     * Reset the builder state to allow reuse.
     */
    public function reset(): self
    {
        $this->tenant = null;
        $this->plan = null;
        $this->previousPlan = null;
        $this->effectiveAt = null;
        $this->reason = null;
        $this->metadata = [];
        $this->channels = ['mail'];

        return $this;
    }

    /**
     * Get the configured reason.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Get the configured metadata.
     *
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Get the configured channels.
     *
     * @return array<int, string>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /**
     * Validate that tenant is set.
     *
     * @throws RuntimeException when tenant is not set
     */
    private function validateTenant(): void
    {
        if ($this->tenant === null) {
            throw new RuntimeException('Tenant is required. Call forTenant() before building.');
        }
    }

    /**
     * Validate that plan is set.
     *
     * @throws RuntimeException when plan is not set
     */
    private function validatePlan(string $planName): void
    {
        if ($this->plan === null) {
            throw new RuntimeException($planName.' is required. Call withPlan() before building.');
        }
    }

    /**
     * Validate that previous plan is set.
     *
     * @throws RuntimeException when previous plan is not set
     */
    private function validatePreviousPlan(): void
    {
        if ($this->previousPlan === null) {
            throw new RuntimeException('Previous plan is required. Call withPreviousPlan() before building.');
        }
    }

    /**
     * Validate that effective date is set.
     *
     * @throws RuntimeException when effective date is not set
     */
    private function validateEffectiveAt(): void
    {
        if ($this->effectiveAt === null) {
            throw new RuntimeException('Effective date is required. Call effectiveAt() before building.');
        }
    }
}
