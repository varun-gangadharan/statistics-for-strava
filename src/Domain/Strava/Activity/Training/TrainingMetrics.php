<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity\Training;

final class TrainingMetrics
{
    /** @var array<string, int|float> */
    private array $atlValues = [];
    /** @var array<string, int|float> */
    private array $ctlValues = [];
    /** @var array<string, int|float> */
    private array $tsbValues = [];
    /** @var array<string, int|float|null> */
    private array $trimpValues = []; // Will now store daily TRIMP (intensity)
    /** @var array<string, int|float|null> */
    private array $monotonyValues = [];
    /** @var array<string, int|float|null> */
    private array $strainValues = [];
    /** @var array<string, int|float> */
    private array $acRatioValues = [];

    private function __construct(
        /** @var array<string, int> */
        private readonly array $intensities,
    ) {
        $this->buildMetrics();
    }

    /**
     * @param array<string, int> $intensities // Keys should be dates/identifiers, values are daily TRIMP/intensity
     */
    public static function create(array $intensities): TrainingMetrics
    {
        return new self($intensities);
    }

    private function buildMetrics(): void
    {
        $alphaATL = 1 - exp(-1 / 7);
        $alphaCTL = 1 - exp(-1 / 42);

        $altValues = $ctlValues = $tsbValues = $trimpValues = $monotonyValues = $strainValues = $acRatioValues = [];

        $delta = 0;
        // Store intensities with numeric keys temporarily for easier slicing by index
        $numericIntensities = array_values($this->intensities);

        foreach ($numericIntensities as $intensity) {
            // Assign daily intensity directly to trimpValues for this delta
            $trimpValues[$delta] = $intensity;

            if (0 === $delta) {
                $altValues[$delta] = $intensity;
                $ctlValues[$delta] = $intensity;
                $tsbValues[$delta] = 0;
            } else {
                $altValues[$delta] = ($intensity * $alphaATL) + ($altValues[$delta - 1] * (1 - $alphaATL));
                $ctlValues[$delta] = ($intensity * $alphaCTL) + ($ctlValues[$delta - 1] * (1 - $alphaCTL));
                $tsbValues[$delta] = $ctlValues[$delta - 1] - $altValues[$delta - 1];
            }

            // Monotony and Strain still depend on a 7-day window
            if ($delta >= 6) { // Day 6 = first full week
                // Use the numeric-keyed array for slicing
                $weekLoads = array_slice($numericIntensities, $delta - 6, 7);
                $sum = array_sum($weekLoads); // Weekly sum needed for Strain calculation
                $avg = $sum / 7;
                $std = $this->standardDeviation($weekLoads);

                // Monotony calculation remains the same
                $monotonyValues[$delta] = $std > 0 ? $avg / $std : 0;
                // Strain calculation uses the weekly sum ($sum) and monotony
                $strainValues[$delta] = $sum * $monotonyValues[$delta];
            } else {
                // No monotony/strain calculation possible before a full week
                $monotonyValues[$delta] = null;
                $strainValues[$delta] = null;
            }

            if (0 == $ctlValues[$delta]) {
                $acRatioValues[$delta] = 0;
            } else {
                $acRatioValues[$delta] = round($altValues[$delta] / $ctlValues[$delta], 2);
            }

            ++$delta;
        }

        $intensityKeys = array_keys($this->intensities); // Get original keys back
        // Round numbers when all calculating is done and combine with original keys.
        $this->acRatioValues = array_combine($intensityKeys, $acRatioValues);
        $this->atlValues = array_combine($intensityKeys, array_map(fn (int|float $value) => round($value, 1), $altValues));
        $this->ctlValues = array_combine($intensityKeys, array_map(fn (int|float $value) => round($value, 1), $ctlValues));
        $this->tsbValues = array_combine($intensityKeys, array_map(fn (int|float $value) => round($value, 1), $tsbValues));
        // Apply rounding/casting to the daily trimp values
        $this->trimpValues = array_combine($intensityKeys, array_map(fn (int|float|null $value) => null === $value ? null : (int) round($value), $trimpValues));
        $this->strainValues = array_combine($intensityKeys, array_map(fn (int|float|null $value) => null === $value ? null : (int) round($value), $strainValues));
        $this->monotonyValues = array_combine($intensityKeys, array_map(fn (int|float|null $value) => null === $value ? null : round($value, 2), $monotonyValues));
    }

    /**
     * @return array<int, int|float> // Note: Changed getter return type description slightly for consistency, not functionality
     */
    public function getAtlValuesForXLastDays(int $numberOfDays): array
    {
        return array_values(array_slice($this->atlValues, -$numberOfDays));
    }

    public function getCurrentAtl(): ?float
    {
        if (empty($this->atlValues)) {
            return null;
        }

        // Use array_pop on a copy to avoid modifying the internal array state
        $copy = $this->atlValues;
        return array_pop($copy);
    }

    /**
     * @return array<int, int|float> // Note: Changed getter return type description slightly for consistency, not functionality
     */
    public function getCtlValuesForXLastDays(int $numberOfDays): array
    {
        return array_values(array_slice($this->ctlValues, -$numberOfDays));
    }

    public function getCurrentCtl(): ?float
    {
        if (empty($this->ctlValues)) {
            return null;
        }

        // Use array_pop on a copy
        $copy = $this->ctlValues;
        return array_pop($copy);
    }

    /**
     * @return array<int, int|float> // Note: Changed getter return type description slightly for consistency, not functionality
     */
    public function getTsbValuesForXLastDays(int $numberOfDays): array
    {
        return array_values(array_slice($this->tsbValues, -$numberOfDays));
    }

    public function getCurrentTsb(): ?float
    {
        if (empty($this->tsbValues)) {
            return null;
        }

        // Use array_pop on a copy
        $copy = $this->tsbValues;
        return array_pop($copy);
    }

    /**
     * @return array<int, int|float|null> // Note: Changed getter return type description slightly for consistency, not functionality
     */
    public function getTrimpValuesForXLastDays(int $numberOfDays): array
    {
        return array_values(array_slice($this->trimpValues, -$numberOfDays));
    }

    // Renamed function to better reflect it returns the *last* daily TRIMP value
    public function getLastDailyTrimp(): ?int // Return type changed to int|null based on array type
    {
        if (empty($this->trimpValues)) {
            return null;
        }

        // Use array_pop on a copy
        $copy = $this->trimpValues;
        $value = array_pop($copy);
        // Ensure it's null or int
        return is_numeric($value) ? (int)$value : null;
    }

    public function getCurrentMonotony(): ?float
    {
        if (empty($this->monotonyValues)) {
            return null;
        }

        // Use array_pop on a copy
        $copy = $this->monotonyValues;
        $value = array_pop($copy);
        return is_numeric($value) ? (float)$value : null; // Ensure float or null
    }

    public function getCurrentStrain(): ?int // Return type changed to int|null based on array type
    {
        if (empty($this->strainValues)) {
            return null;
        }

        // Use array_pop on a copy
        $copy = $this->strainValues;
        $value = array_pop($copy);
         // Ensure it's null or int
        return is_numeric($value) ? (int)$value : null;
    }

    public function getCurrentAcRatio(): ?float
    {
        if (empty($this->acRatioValues)) {
            return null;
        }

         // Use array_pop on a copy
        $copy = $this->acRatioValues;
        return array_pop($copy); // Already float or 0
    }

    /**
     * @param array<int, int|float> $values // Changed signature to reflect usage with numerically indexed slice
     */
    private function standardDeviation(array $values): float
    {
        $count = count($values);
        if ($count === 0) {
            return 0.0; // Avoid division by zero
        }
        $mean = array_sum($values) / $count;
        $sumSquares = 0;
        foreach ($values as $v) {
            $sumSquares += pow($v - $mean, 2);
        }

        // Use population standard deviation as per original logic
        return sqrt($sumSquares / $count);
    }
}
