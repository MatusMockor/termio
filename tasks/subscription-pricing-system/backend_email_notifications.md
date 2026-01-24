# Email Notification System

**PRD Source**: `prds/2026-01-subscription-pricing-system.md`
**Category**: Backend
**Complexity**: Medium
**Dependencies**: `backend_subscription_service.md`, `backend_billing_invoicing.md`, `backend_webhook_handling.md`
**Status**: Not Started

## Technical Overview

**Summary**: Implement email notifications for subscription lifecycle events including trial reminders, payment success/failure, plan changes, and usage warnings. Uses Laravel notifications with queued delivery per PRD Section 7.

**Architecture Impact**: Adds notification classes for all subscription events. Integrates with existing email service. Scheduled jobs for trial reminders.

**Risk Assessment**:
- **Medium**: Email delivery reliability - queue with retries
- **Low**: Template consistency - use shared layouts
- **Low**: Timing of scheduled notifications

## Component Architecture

### Notification Classes

**File**: `app/Notifications/TrialStartedNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TrialStartedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $planName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Welcome to Termio ' . $this->planName . ' - Your 14-Day Trial Has Started!')
            ->greeting('Welcome to Termio!')
            ->line("You've started a 14-day free trial of the {$this->planName} plan for {$this->tenant->name}.")
            ->line('During your trial, you have full access to all ' . $this->planName . ' features:')
            ->line('- Unlimited service creation')
            ->line('- Advanced calendar views')
            ->line('- Email reminders for clients')
            ->line('- And much more!')
            ->action('Explore Your Dashboard', url('/dashboard'))
            ->line('Your trial ends in 14 days. Add a payment method anytime to continue after the trial.')
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/TrialEndingNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TrialEndingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly int $daysRemaining = 3,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->daysRemaining === 1
            ? 'Your Termio trial ends tomorrow!'
            : "Your Termio trial ends in {$this->daysRemaining} days";

        return (new MailMessage())
            ->subject($subject)
            ->greeting('Hello!')
            ->line("Your free trial for {$this->tenant->name} is ending " . ($this->daysRemaining === 1 ? 'tomorrow' : "in {$this->daysRemaining} days") . '.')
            ->line('To continue using all features without interruption, add a payment method now.')
            ->action('Add Payment Method', url('/billing/payment-methods'))
            ->line('If you don\'t add a payment method, your account will be downgraded to the FREE plan.')
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/TrialEndedNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class TrialEndedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly bool $converted = false,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        if ($this->converted) {
            return (new MailMessage())
                ->subject('Welcome to Termio - Your Subscription is Now Active!')
                ->greeting('Thank you for choosing Termio!')
                ->line("Your trial has ended and your subscription for {$this->tenant->name} is now active.")
                ->line('You\'ll be charged automatically each billing period.')
                ->action('View Your Subscription', url('/subscription'))
                ->salutation('The Termio Team');
        }

        return (new MailMessage())
            ->subject('Your Termio Trial Has Ended')
            ->greeting('Hello!')
            ->line("Your free trial for {$this->tenant->name} has ended.")
            ->line('Your account has been moved to the FREE plan with limited features.')
            ->line('Upgrade anytime to unlock all features again!')
            ->action('View Plans', url('/subscription/plans'))
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/PaymentSuccessNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Invoice;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Invoice $invoice,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Payment Received - Invoice ' . $this->invoice->invoice_number)
            ->greeting('Payment Confirmed!')
            ->line("We've received your payment of {$this->invoice->amount_gross} {$this->invoice->currency} for {$this->tenant->name}.")
            ->line('Invoice Number: ' . $this->invoice->invoice_number)
            ->line('Date: ' . $this->invoice->created_at->format('d.m.Y'))
            ->action('Download Invoice', url('/billing/invoices/' . $this->invoice->id . '/download'))
            ->line('Thank you for using Termio!')
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/PaymentFailedNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly int $attemptCount = 1,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $attemptsRemaining = self::MAX_ATTEMPTS - $this->attemptCount;

        $message = (new MailMessage())
            ->subject('Payment Failed - Action Required')
            ->greeting('Payment Issue');

        if ($attemptsRemaining > 0) {
            $message->line("We couldn't process your payment for {$this->tenant->name}.")
                ->line("We'll retry automatically, but please update your payment method to avoid service interruption.")
                ->line("Attempts remaining: {$attemptsRemaining}");
        } else {
            $message->line("We couldn't process your payment for {$this->tenant->name} after multiple attempts.")
                ->line('Your subscription has been cancelled and your account moved to the FREE plan.')
                ->line('Update your payment method and resubscribe to restore access.');
        }

        return $message
            ->action('Update Payment Method', url('/billing/payment-methods'))
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/SubscriptionUpgradedNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionUpgradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Plan $newPlan,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Upgrade Confirmed - Welcome to ' . $this->newPlan->name)
            ->greeting('Upgrade Successful!')
            ->line("You've successfully upgraded {$this->tenant->name} to the {$this->newPlan->name} plan.")
            ->line('Your new features and limits are now active!')
            ->action('Explore New Features', url('/dashboard'))
            ->line('Thank you for growing with Termio!')
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/SubscriptionDowngradeScheduledNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Plan;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionDowngradeScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Plan $newPlan,
        private readonly Carbon $effectiveDate,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Downgrade Scheduled - Effective ' . $this->effectiveDate->format('d.m.Y'))
            ->greeting('Downgrade Confirmed')
            ->line("Your request to downgrade {$this->tenant->name} to the {$this->newPlan->name} plan has been scheduled.")
            ->line('Effective date: ' . $this->effectiveDate->format('d.m.Y'))
            ->line('You will continue to have access to all current features until this date.')
            ->action('Cancel Downgrade', url('/subscription'))
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/SubscriptionDowngradedNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionDowngradedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Plan $newPlan,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Plan Changed to ' . $this->newPlan->name)
            ->greeting('Plan Updated')
            ->line("Your subscription for {$this->tenant->name} has been changed to the {$this->newPlan->name} plan.")
            ->line('Your new limits are now in effect.')
            ->action('View Your Plan', url('/subscription'))
            ->line('You can upgrade anytime to access more features.')
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/SubscriptionCanceledNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionCanceledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly Carbon $accessEndsAt,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Subscription Cancelled - Access Until ' . $this->accessEndsAt->format('d.m.Y'))
            ->greeting('Subscription Cancelled')
            ->line("Your subscription for {$this->tenant->name} has been cancelled.")
            ->line('You will continue to have access until: ' . $this->accessEndsAt->format('d.m.Y'))
            ->line('After this date, your account will be moved to the FREE plan.')
            ->action('Reactivate Subscription', url('/subscription'))
            ->line('We hope to see you again!')
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/SubscriptionEndedNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class SubscriptionEndedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your Subscription Has Ended')
            ->greeting('Subscription Ended')
            ->line("Your paid subscription for {$this->tenant->name} has ended.")
            ->line('Your account has been moved to the FREE plan with basic features.')
            ->line('Subscribe again anytime to unlock all features!')
            ->action('View Plans', url('/subscription/plans'))
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/UsageWarningNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class UsageWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly int $percentage = 80,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject("You've used {$this->percentage}% of your monthly reservations")
            ->greeting('Usage Alert')
            ->line("You've used {$this->percentage}% of your monthly reservation limit for {$this->tenant->name}.")
            ->line('Consider upgrading to avoid running out of reservations this month.')
            ->action('Upgrade Your Plan', url('/subscription/plans'))
            ->line('Your usage resets on the 1st of each month.')
            ->salutation('The Termio Team');
    }
}
```

**File**: `app/Notifications/CardExpiringNotification.php`

```php
<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class CardExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Tenant $tenant,
        private readonly string $cardLast4,
        private readonly int $expiryMonth,
        private readonly int $expiryYear,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Your Card is Expiring Soon')
            ->greeting('Payment Method Expiring')
            ->line("The card ending in {$this->cardLast4} for {$this->tenant->name} expires on {$this->expiryMonth}/{$this->expiryYear}.")
            ->line('Please update your payment method to avoid any service interruption.')
            ->action('Update Payment Method', url('/billing/payment-methods'))
            ->salutation('The Termio Team');
    }
}
```

### Scheduled Jobs

**File**: `app/Jobs/Subscription/SendTrialRemindersJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Contracts\Repositories\SubscriptionRepository;
use App\Notifications\TrialEndingNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendTrialRemindersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $daysRemaining,
    ) {}

    public function handle(SubscriptionRepository $subscriptions): void
    {
        $trials = $subscriptions->getTrialsEndingSoon($this->daysRemaining);

        foreach ($trials as $subscription) {
            $tenant = $subscription->tenant;
            $owner = $tenant->owner;

            if ($owner) {
                $owner->notify(new TrialEndingNotification($tenant, $this->daysRemaining));
            }
        }
    }
}
```

**File**: `app/Jobs/Subscription/CheckExpiringCardsJob.php`

```php
<?php

declare(strict_types=1);

namespace App\Jobs\Subscription;

use App\Models\PaymentMethod;
use App\Notifications\CardExpiringNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class CheckExpiringCardsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $nextMonth = now()->addMonth();

        PaymentMethod::where('is_default', true)
            ->where('card_exp_year', $nextMonth->year)
            ->where('card_exp_month', $nextMonth->month)
            ->with('tenant.owner')
            ->chunk(100, static function ($paymentMethods): void {
                foreach ($paymentMethods as $pm) {
                    $owner = $pm->tenant->owner;

                    if ($owner) {
                        $owner->notify(new CardExpiringNotification(
                            $pm->tenant,
                            $pm->card_last4,
                            $pm->card_exp_month,
                            $pm->card_exp_year
                        ));
                    }
                }
            });
    }
}
```

### Schedule Configuration

**File**: `routes/console.php`

```php
use App\Jobs\Subscription\CheckExpiringCardsJob;
use App\Jobs\Subscription\CheckUsageWarningsJob;
use App\Jobs\Subscription\ProcessScheduledDowngradesJob;
use App\Jobs\Subscription\ProcessTrialExpirationsJob;
use App\Jobs\Subscription\SendTrialRemindersJob;
use Illuminate\Support\Facades\Schedule;

// Trial reminders - 3 days before
Schedule::job(new SendTrialRemindersJob(3))->dailyAt('09:00');

// Trial reminders - 1 day before
Schedule::job(new SendTrialRemindersJob(1))->dailyAt('09:00');

// Process expired trials
Schedule::job(new ProcessTrialExpirationsJob())->hourly();

// Process scheduled downgrades
Schedule::job(new ProcessScheduledDowngradesJob())->hourly();

// Check for expiring cards - monthly
Schedule::job(new CheckExpiringCardsJob())->monthlyOn(1, '09:00');

// Usage warnings - daily
Schedule::job(new CheckUsageWarningsJob())->dailyAt('10:00');
```

## Email Templates Summary

| Event | Class | Trigger |
|-------|-------|---------|
| Trial Started | `TrialStartedNotification` | Subscription created with trial |
| Trial Ending (3 days) | `TrialEndingNotification` | Scheduled job |
| Trial Ending (1 day) | `TrialEndingNotification` | Scheduled job |
| Trial Ended | `TrialEndedNotification` | Trial expiration job |
| Payment Success | `PaymentSuccessNotification` | Webhook: invoice.paid |
| Payment Failed | `PaymentFailedNotification` | Webhook: invoice.payment_failed |
| Upgrade Complete | `SubscriptionUpgradedNotification` | Upgrade action |
| Downgrade Scheduled | `SubscriptionDowngradeScheduledNotification` | Downgrade action |
| Downgrade Executed | `SubscriptionDowngradedNotification` | Scheduled job |
| Cancellation Scheduled | `SubscriptionCanceledNotification` | Cancel action |
| Subscription Ended | `SubscriptionEndedNotification` | Webhook: subscription.deleted |
| Usage at 80% | `UsageWarningNotification` | Usage check job |
| Card Expiring | `CardExpiringNotification` | Monthly check job |

## Testing Strategy

### E2E Test
- `TestSubscriptionNotifications` covering trial reminders, payment notifications
- Verify: Notifications queued, correct content, delivered to owner

### Manual Verification
- Trigger trial reminder job
- Create payment and verify receipt email
- Check email content and formatting

## Implementation Steps

1. **Medium** - Create TrialStartedNotification
2. **Small** - Create TrialEndingNotification
3. **Small** - Create TrialEndedNotification
4. **Medium** - Create PaymentSuccessNotification
5. **Medium** - Create PaymentFailedNotification
6. **Small** - Create SubscriptionUpgradedNotification
7. **Small** - Create SubscriptionDowngradeScheduledNotification
8. **Small** - Create SubscriptionDowngradedNotification
9. **Small** - Create SubscriptionCanceledNotification
10. **Small** - Create SubscriptionEndedNotification
11. **Small** - Create UsageWarningNotification
12. **Small** - Create CardExpiringNotification
13. **Medium** - Create SendTrialRemindersJob
14. **Small** - Create CheckExpiringCardsJob
15. **Medium** - Add schedule configuration to routes/console.php
16. **Medium** - Wire notifications into actions and webhook handlers
17. **Medium** - Write feature tests for notifications
18. **Small** - Run Pint and verify code style

## Cross-Task Dependencies

- **Depends on**: `backend_subscription_service.md`, `backend_billing_invoicing.md`, `backend_webhook_handling.md`
- **Blocks**: None - this is a supporting task
- **Parallel work**: Can work alongside `frontend_*` tasks
