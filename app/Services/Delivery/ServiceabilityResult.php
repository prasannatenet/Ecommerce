<?php

namespace App\Services\Delivery;

/**
 * The parsed answer to "can Delhivery deliver to this pincode?".
 *
 * Delhivery returns HTTP 200 with an empty delivery_codes array for an
 * unserviceable pincode, so a plain $response->successful() check reports every
 * pincode as serviceable. This value object carries the details the admin panel
 * needs: is it serviceable, is COD allowed, is prepaid allowed, and what does
 * Delhivery say about it.
 */
final class ServiceabilityResult
{
    public function __construct(
        public readonly bool $serviceable,
        public readonly bool $codAvailable = true,
        public readonly bool $prepaidAvailable = true,
        public readonly bool $isOda = false,
        public readonly ?float $maxWeightKg = null,
        public readonly ?string $city = null,
        public readonly ?string $district = null,
        public readonly ?string $stateCode = null,
        public readonly ?string $remarks = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function failure(string $error): self
    {
        return new self(false, error: $error);
    }

    /**
     * Warnings worth showing the operator even when the pincode is serviceable.
     */
    public function warnings(): array
    {
        $warnings = [];

        if ($this->serviceable && ! $this->codAvailable) {
            $warnings[] = 'Cash on Delivery is NOT available for this pincode.';
        }

        if ($this->serviceable && ! $this->prepaidAvailable) {
            $warnings[] = 'Prepaid delivery is NOT available for this pincode.';
        }

        if ($this->serviceable && $this->remarks !== null && trim($this->remarks) !== '') {
            $warnings[] = 'Courier note: ' . trim($this->remarks);
        }

        return $warnings;
    }

    public function summary(): string
    {
        if ($this->error !== null) {
            return $this->error;
        }

        if (! $this->serviceable) {
            return 'Delivery is not available for this pincode.';
        }

        $parts = ['Serviceable'];

        if ($this->city) {
            $parts[] = $this->city;
        }

        // Delhivery reports max_weight 0 to mean "no limit". Rendering that as
        // "0 kg" would be wrong and would read as a rejection.
        if ($this->maxWeightKg !== null && $this->maxWeightKg > 0) {
            $parts[] = 'max ' . rtrim(rtrim(number_format($this->maxWeightKg, 2, '.', ''), '0'), '.') . ' kg';
        }

        if ($this->warnings() !== []) {
            $parts[] = implode(' ', $this->warnings());
        }

        return implode(' — ', $parts);
    }
}
