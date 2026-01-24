<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\Services\VatService as VatServiceContract;
use App\DTOs\Billing\VatCalculation;
use App\Models\Tenant;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SoapClient;

final class VatService implements VatServiceContract
{
    private const float SLOVAKIA_VAT_RATE = 0.20;

    private const array EU_COUNTRIES = [
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
    ];

    private const string VIES_WSDL = 'https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl';

    public function calculateVat(Tenant $tenant, float $amount): VatCalculation
    {
        $vatRate = $this->getVatRate($tenant);
        $vatAmount = round($amount * $vatRate, 2);
        $grossAmount = round($amount + $vatAmount, 2);
        $reverseCharge = $this->isReverseCharge($tenant);

        $note = $this->getVatNote($tenant, $reverseCharge);

        return new VatCalculation(
            netAmount: $amount,
            vatRate: $vatRate,
            vatAmount: $vatAmount,
            grossAmount: $grossAmount,
            reverseCharge: $reverseCharge,
            note: $note,
        );
    }

    public function getVatRate(Tenant $tenant): float
    {
        $country = $tenant->country ?? 'SK';

        // Slovakia - always 20% VAT
        if ($country === 'SK') {
            return self::SLOVAKIA_VAT_RATE;
        }

        // EU with valid VAT ID - reverse charge (0%)
        if ($this->isEuCountry($country) && $this->isReverseCharge($tenant)) {
            return 0.00;
        }

        // EU without valid VAT ID - Slovak VAT rate
        if ($this->isEuCountry($country)) {
            return self::SLOVAKIA_VAT_RATE;
        }

        // Non-EU - no VAT
        return 0.00;
    }

    public function validateVatId(string $vatId, string $countryCode): bool
    {
        $vatId = strtoupper(preg_replace('/\s+/', '', $vatId) ?? '');

        if (mb_strlen($vatId) < 4) {
            return false;
        }

        $countryCode = strtoupper($countryCode);

        if (! $this->isEuCountry($countryCode)) {
            return false;
        }

        // Remove country code prefix if present
        $vatNumber = str_starts_with($vatId, $countryCode)
            ? mb_substr($vatId, 2)
            : $vatId;

        // Check cache first
        $cacheKey = 'vat_validation_'.$countryCode.'_'.$vatNumber;
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return (bool) $cached;
        }

        try {
            $result = $this->validateViaVies($countryCode, $vatNumber);
            Cache::put($cacheKey, $result, now()->addHours(24));

            return $result;
        } catch (Exception $e) {
            Log::warning('VIES validation failed', [
                'vat_id' => $vatId,
                'country_code' => $countryCode,
                'error' => $e->getMessage(),
            ]);

            // Return false on API failure - treat as invalid
            return false;
        }
    }

    public function isReverseCharge(Tenant $tenant): bool
    {
        $country = $tenant->country ?? 'SK';

        // Reverse charge only applies to EU B2B with valid VAT ID (not Slovakia)
        if ($country === 'SK') {
            return false;
        }

        if (! $this->isEuCountry($country)) {
            return false;
        }

        $vatId = $tenant->vat_id;

        if (empty($vatId)) {
            return false;
        }

        // Check if VAT ID was verified
        if ($tenant->vat_id_verified_at !== null) {
            return true;
        }

        // Validate VAT ID
        return $this->validateVatId($vatId, $country);
    }

    public function isEuCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), self::EU_COUNTRIES, true);
    }

    /**
     * Validate VAT ID via VIES SOAP API.
     *
     * @throws Exception
     */
    private function validateViaVies(string $countryCode, string $vatNumber): bool
    {
        $client = new SoapClient(self::VIES_WSDL, [
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 10,
        ]);

        $result = $client->checkVat([
            'countryCode' => $countryCode,
            'vatNumber' => $vatNumber,
        ]);

        return $result->valid === true;
    }

    /**
     * Get the VAT note for the invoice.
     */
    private function getVatNote(Tenant $tenant, bool $reverseCharge): ?string
    {
        $country = $tenant->country ?? 'SK';

        if ($reverseCharge) {
            return 'Reverse charge - VAT to be accounted for by the recipient';
        }

        if (! $this->isEuCountry($country) && $country !== 'SK') {
            return 'Export - VAT exempt';
        }

        return null;
    }
}
