<?php

declare(strict_types=1);

namespace App\Enums;

enum Feature: string
{
    // Customization features
    case CustomLogo = 'custom_logo';
    case CustomColors = 'custom_colors';
    case CustomBookingUrl = 'custom_booking_url';
    case CustomDomain = 'custom_domain';
    case WhiteLabel = 'white_label';

    // Integration features
    case GoogleCalendarSync = 'google_calendar_sync';
    case PaymentGateway = 'payment_gateway';
    case ApiAccess = 'api_access';
    case ZapierIntegration = 'zapier_integration';

    // Advanced features
    case MultiLanguage = 'multi_language';
    case StaffPermissions = 'staff_permissions';
    case ClientSegmentation = 'client_segmentation';
    case WaitlistManagement = 'waitlist_management';
    case RecurringAppointments = 'recurring_appointments';
    case GiftVouchers = 'gift_vouchers';

    // Notification features
    case SmsReminders = 'sms_reminders';
    case EmailReminders = 'email_reminders';

    /**
     * Get the minimum plan required for this feature.
     */
    public function getMinimumPlan(): string
    {
        return match ($this) {
            // EASY tier features
            self::CustomLogo,
            self::CustomColors,
            self::CustomBookingUrl,
            self::GoogleCalendarSync,
            self::PaymentGateway,
            self::MultiLanguage,
            self::RecurringAppointments,
            self::EmailReminders => 'easy',

            // SMART tier features
            self::CustomDomain,
            self::ApiAccess,
            self::ZapierIntegration,
            self::StaffPermissions,
            self::ClientSegmentation,
            self::WaitlistManagement,
            self::GiftVouchers,
            self::SmsReminders => 'smart',

            // PREMIUM tier features
            self::WhiteLabel => 'premium',
        };
    }

    /**
     * Get human-readable feature name.
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::CustomLogo => 'Custom Logo',
            self::CustomColors => 'Custom Colors',
            self::CustomBookingUrl => 'Custom Booking URL',
            self::CustomDomain => 'Custom Domain',
            self::WhiteLabel => 'White Label',
            self::GoogleCalendarSync => 'Google Calendar Sync',
            self::PaymentGateway => 'Payment Gateway',
            self::ApiAccess => 'API Access',
            self::ZapierIntegration => 'Zapier Integration',
            self::MultiLanguage => 'Multi-Language Support',
            self::StaffPermissions => 'Staff Permissions',
            self::ClientSegmentation => 'Client Segmentation',
            self::WaitlistManagement => 'Waitlist Management',
            self::RecurringAppointments => 'Recurring Appointments',
            self::GiftVouchers => 'Gift Vouchers',
            self::SmsReminders => 'SMS Reminders',
            self::EmailReminders => 'Email Reminders',
        };
    }

    /**
     * Get the feature category.
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::CustomLogo,
            self::CustomColors,
            self::CustomBookingUrl,
            self::CustomDomain,
            self::WhiteLabel => 'customization',

            self::GoogleCalendarSync,
            self::PaymentGateway,
            self::ApiAccess,
            self::ZapierIntegration => 'integrations',

            self::MultiLanguage,
            self::StaffPermissions,
            self::ClientSegmentation,
            self::WaitlistManagement,
            self::RecurringAppointments,
            self::GiftVouchers => 'advanced_features',

            self::SmsReminders,
            self::EmailReminders => 'notifications',
        };
    }

    /**
     * Try to create a Feature from a string value.
     */
    public static function tryFromString(string $value): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }

        return null;
    }
}
