<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

final class EndTimeAfterStartTime implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $startTime = $this->resolveStartTime($attribute);

        if ($startTime === null) {
            return;
        }

        $referenceDate = $this->resolveReferenceDate();

        if ($referenceDate === null) {
            return;
        }

        if ($this->isEndTimeAfterStartTime($referenceDate, $startTime, $value)) {
            return;
        }

        $fail('The :attribute must be after start time.');
    }

    private function resolveStartTime(string $attribute): ?string
    {
        $startAttribute = preg_replace('/\.end_time$/', '.start_time', $attribute);

        if (! is_string($startAttribute)) {
            return null;
        }

        $startTime = data_get($this->data, $startAttribute);

        if (! is_string($startTime)) {
            return null;
        }

        return $startTime;
    }

    private function resolveReferenceDate(): ?string
    {
        $referenceDate = config('working_hours.time_reference_date');

        if ($this->isNonEmptyString($referenceDate)) {
            return $referenceDate;
        }

        $defaultReferenceDate = config('working_hours.default_time_reference_date');

        if (! $this->isNonEmptyString($defaultReferenceDate)) {
            return null;
        }

        return $defaultReferenceDate;
    }

    private function isEndTimeAfterStartTime(string $referenceDate, string $startTime, string $endTime): bool
    {
        $normalizedStart = $this->normalizeTime($referenceDate, $startTime);

        if ($normalizedStart === null) {
            return true;
        }

        $normalizedEnd = $this->normalizeTime($referenceDate, $endTime);

        if ($normalizedEnd === null) {
            return true;
        }

        return $normalizedEnd > $normalizedStart;
    }

    private function normalizeTime(string $referenceDate, string $time): ?int
    {
        $normalizedTime = strtotime($referenceDate.' '.$time);

        if ($normalizedTime === false) {
            return null;
        }

        return $normalizedTime;
    }

    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && $value !== '';
    }
}
