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
        $country = $this->getTenantCountry($tenant);

        if ($this->isSlovakia($country)) {
            return $this->getSlovakiaVatRate();
        }

        if ($this->isEuCountry($country)) {
            return $this->getEuVatRate($tenant);
        }

        return 0.00;
    }

    public function validateVatId(string $vatId, string $countryCode): bool
    {
        $normalizedVatId = $this->normalizeVatId($vatId);

        if (! $this->isValidVatIdFormat($normalizedVatId)) {
            return false;
        }

        $countryCode = strtoupper($countryCode);

        if (! $this->isEuCountry($countryCode)) {
            return false;
        }

        $vatNumber = $this->extractVatNumber($normalizedVatId, $countryCode);

        return $this->validateVatIdWithCache($vatNumber, $countryCode, $normalizedVatId);
    }

    public function isReverseCharge(Tenant $tenant): bool
    {
        $country = $this->getTenantCountry($tenant);

        if ($this->isSlovakia($country)) {
            return false;
        }

        if (! $this->isEuCountry($country)) {
            return false;
        }

        if (! $this->hasVatId($tenant)) {
            return false;
        }

        if ($this->isVatIdVerified($tenant)) {
            return true;
        }

        return $this->validateVatId((string) $tenant->vat_id, $country);
    }

    public function isEuCountry(string $countryCode): bool
    {
        return in_array(strtoupper($countryCode), config('subscription.vat.eu_countries'), true);
    }

    /**
     * Validate VAT ID via VIES SOAP API.
     *
     * @throws Exception
     */
    private function validateViaVies(string $countryCode, string $vatNumber): bool
    {
        $client = new SoapClient(config('subscription.vat.vies_wsdl'), [
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
        if ($reverseCharge) {
            return 'Reverse charge - VAT to be accounted for by the recipient';
        }

        $country = $this->getTenantCountry($tenant);

        if ($this->isNonEuExport($country)) {
            return 'Export - VAT exempt';
        }

        return null;
    }

    private function getTenantCountry(Tenant $tenant): string
    {
        return $tenant->country ?? 'SK';
    }

    private function isSlovakia(string $country): bool
    {
        return $country === 'SK';
    }

    private function getSlovakiaVatRate(): float
    {
        return (float) config('subscription.vat.slovakia_rate');
    }

    private function getEuVatRate(Tenant $tenant): float
    {
        if ($this->isReverseCharge($tenant)) {
            return 0.00;
        }

        return $this->getSlovakiaVatRate();
    }

    private function hasVatId(Tenant $tenant): bool
    {
        return ! empty($tenant->vat_id);
    }

    private function isVatIdVerified(Tenant $tenant): bool
    {
        return $tenant->vat_id_verified_at !== null;
    }

    private function normalizeVatId(string $vatId): string
    {
        return strtoupper(preg_replace('/\s+/', '', $vatId) ?? '');
    }

    private function isValidVatIdFormat(string $vatId): bool
    {
        return mb_strlen($vatId) >= 4;
    }

    private function extractVatNumber(string $vatId, string $countryCode): string
    {
        if (str_starts_with($vatId, $countryCode)) {
            return mb_substr($vatId, 2);
        }

        return $vatId;
    }

    private function validateVatIdWithCache(string $vatNumber, string $countryCode, string $originalVatId): bool
    {
        $cacheKey = $this->buildVatCacheKey($countryCode, $vatNumber);
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return (bool) $cached;
        }

        try {
            $result = $this->validateViaVies($countryCode, $vatNumber);
            $this->cacheVatValidationResult($cacheKey, $result);

            return $result;
        } catch (Exception $e) {
            $this->logVatValidationFailure($originalVatId, $countryCode, $e);

            return false;
        }
    }

    private function buildVatCacheKey(string $countryCode, string $vatNumber): string
    {
        return 'vat_validation_'.$countryCode.'_'.$vatNumber;
    }

    private function cacheVatValidationResult(string $cacheKey, bool $result): void
    {
        Cache::put($cacheKey, $result, now()->addHours(24));
    }

    private function logVatValidationFailure(string $vatId, string $countryCode, Exception $exception): void
    {
        Log::warning('VIES validation failed', [
            'vat_id' => $vatId,
            'country_code' => $countryCode,
            'error' => $exception->getMessage(),
        ]);
    }

    private function isNonEuExport(string $country): bool
    {
        return ! $this->isEuCountry($country) && ! $this->isSlovakia($country);
    }
}
