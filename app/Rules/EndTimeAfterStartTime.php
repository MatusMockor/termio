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

        $startAttribute = preg_replace('/\.end_time$/', '.start_time', $attribute);

        if (! is_string($startAttribute)) {
            return;
        }

        $startTime = data_get($this->data, $startAttribute);

        if (! is_string($startTime)) {
            return;
        }

        $normalizedStart = strtotime('1970-01-01 '.$startTime);
        $normalizedEnd = strtotime('1970-01-01 '.$value);

        if ($normalizedStart === false || $normalizedEnd === false) {
            return;
        }

        if ($normalizedEnd > $normalizedStart) {
            return;
        }

        $fail('The :attribute must be after start time.');
    }
}
