<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PlanSlug;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Plan
 *
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
final class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $_request): array
    {
        $monthlyPrice = (float) $this->monthly_price;
        $yearlyPrice = (float) $this->yearly_price;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'monthly_price' => $this->monthly_price,
            'yearly_price' => $this->yearly_price,
            'pricing' => [
                'monthly' => $monthlyPrice,
                'yearly' => $yearlyPrice,
                'yearly_monthly_equivalent' => $this->getYearlyMonthlyEquivalent(),
                'yearly_discount_percent' => $this->getYearlyDiscountPercentage(),
                'currency' => 'EUR',
            ],
            'pricing_details' => [
                'monthly' => [
                    'amount' => $monthlyPrice,
                    'currency' => 'EUR',
                ],
                'yearly' => [
                    'amount' => $yearlyPrice,
                    'monthly_equivalent' => $this->getYearlyMonthlyEquivalent(),
                    'discount_percentage' => $this->getYearlyDiscountPercentage(),
                    'currency' => 'EUR',
                ],
            ],
            'features' => $this->features,
            'limits' => $this->formatLimits(),
            'is_popular' => $this->slug === PlanSlug::Smart->value,
        ];
    }

    /**
     * Get yearly discount percentage.
     */
    private function getYearlyDiscountPercentage(): float
    {
        $monthlyPrice = (float) $this->monthly_price;
        $yearlyPrice = (float) $this->yearly_price;

        if ($monthlyPrice <= 0) {
            return 0.0;
        }

        $annualMonthlyTotal = $monthlyPrice * 12;
        $savings = $annualMonthlyTotal - $yearlyPrice;

        return round(($savings / $annualMonthlyTotal) * 100, 0);
    }

    /**
     * Get monthly equivalent of yearly price.
     */
    private function getYearlyMonthlyEquivalent(): float
    {
        return round((float) $this->yearly_price / 12, 2);
    }

    /**
     * Format limits for display (replace -1 with 'unlimited').
     *
     * @return array<string, int|string>
     */
    private function formatLimits(): array
    {
        $formatted = [];

        foreach ($this->limits as $key => $value) {
            $formatted[$key] = $value === -1 ? 'unlimited' : $value;
        }

        return $formatted;
    }
}
