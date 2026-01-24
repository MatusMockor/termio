<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Billing\VatCalculation;
use App\Models\Tenant;

interface VatService
{
    /**
     * Calculate VAT for a given tenant and amount.
     */
    public function calculateVat(Tenant $tenant, float $amount): VatCalculation;

    /**
     * Get VAT rate for a tenant based on their country and VAT ID.
     *
     * @return float The VAT rate as a decimal (e.g., 0.20 for 20%)
     */
    public function getVatRate(Tenant $tenant): float;

    /**
     * Validate a VAT ID using VIES API.
     */
    public function validateVatId(string $vatId, string $countryCode): bool;

    /**
     * Check if reverse charge applies for the tenant.
     */
    public function isReverseCharge(Tenant $tenant): bool;

    /**
     * Check if a country is an EU member state.
     */
    public function isEuCountry(string $countryCode): bool;
}
