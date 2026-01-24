<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\Feature;
use PHPUnit\Framework\TestCase;

final class FeatureEnumTest extends TestCase
{
    public function test_google_calendar_sync_requires_easy_plan(): void
    {
        $this->assertEquals('easy', Feature::GoogleCalendarSync->getMinimumPlan());
    }

    public function test_api_access_requires_smart_plan(): void
    {
        $this->assertEquals('smart', Feature::ApiAccess->getMinimumPlan());
    }

    public function test_white_label_requires_premium_plan(): void
    {
        $this->assertEquals('premium', Feature::WhiteLabel->getMinimumPlan());
    }

    public function test_try_from_string_returns_feature_for_valid_value(): void
    {
        $feature = Feature::tryFromString('google_calendar_sync');

        $this->assertNotNull($feature);
        $this->assertEquals(Feature::GoogleCalendarSync, $feature);
    }

    public function test_try_from_string_returns_null_for_invalid_value(): void
    {
        $feature = Feature::tryFromString('unknown_feature');

        $this->assertNull($feature);
    }

    public function test_get_label_returns_human_readable_name(): void
    {
        $this->assertEquals('Google Calendar Sync', Feature::GoogleCalendarSync->getLabel());
        $this->assertEquals('Custom Logo', Feature::CustomLogo->getLabel());
        $this->assertEquals('API Access', Feature::ApiAccess->getLabel());
    }

    public function test_get_category_returns_correct_category(): void
    {
        $this->assertEquals('integrations', Feature::GoogleCalendarSync->getCategory());
        $this->assertEquals('customization', Feature::CustomLogo->getCategory());
        $this->assertEquals('notifications', Feature::SmsReminders->getCategory());
        $this->assertEquals('advanced_features', Feature::StaffPermissions->getCategory());
    }

    public function test_all_features_have_minimum_plan(): void
    {
        $validPlans = ['easy', 'smart', 'premium'];

        foreach (Feature::cases() as $feature) {
            $this->assertContains(
                $feature->getMinimumPlan(),
                $validPlans,
                "Feature {$feature->value} has invalid minimum plan"
            );
        }
    }

    public function test_all_features_have_category(): void
    {
        $validCategories = ['customization', 'integrations', 'advanced_features', 'notifications'];

        foreach (Feature::cases() as $feature) {
            $this->assertContains(
                $feature->getCategory(),
                $validCategories,
                "Feature {$feature->value} has invalid category"
            );
        }
    }

    public function test_all_features_have_label(): void
    {
        foreach (Feature::cases() as $feature) {
            $label = $feature->getLabel();
            $this->assertNotEmpty($label, "Feature {$feature->value} has empty label");
            $this->assertIsString($label);
        }
    }
}
