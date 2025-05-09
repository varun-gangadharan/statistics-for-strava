<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

final class TrainingMetrics
{
    /** @var array<int, int|float> */
    private array $atlValues = [];
    /** @var array<int, int|float> */
    private array $ctlValues = [];
    /** @var array<int, int|float> */
    private array $tsbValues = [];
    /** @var array<int, int|float|null> */
    private array $trimpValues = [];
    /** @var array<int, int|float|null> */
    private array $monotonyValues = [];
    /** @var array<int, int|float|null> */
    private array $strainValues = [];
    /** @var array<int, int|float> */
    private array $acRatioValues = [];

    private function __construct(
        /** @var array<int, int> */
        private readonly array $intensities,
    ) {
        $this->buildMetrics();
    }

    /**
     * @param array<int, int> $intensities
     */
    public static function create(array $intensities): TrainingMetrics
    {
        return new self($intensities);
    }

    private function buildMetrics(): void
    {
        $alphaATL = 1 - exp(-1 / 7);
        $alphaCTL = 1 - exp(-1 / 42);

        foreach ($this->intensities as $day => $load) {
            if (0 === $day) {
                $this->atlValues[$day] = $load;
                $this->ctlValues[$day] = $load;
                $this->tsbValues[$day] = 0;
            } else {
                $this->atlValues[$day] = ($load * $alphaATL) + ($this->atlValues[$day - 1] * (1 - $alphaATL));
                $this->ctlValues[$day] = ($load * $alphaCTL) + ($this->ctlValues[$day - 1] * (1 - $alphaCTL));
                $this->tsbValues[$day] = $this->ctlValues[$day - 1] - $this->atlValues[$day - 1];
            }

            if ($day >= 6) { // Day 6 = first full week
                $weekLoads = array_slice($this->intensities, $day - 6, 7);
                $sum = array_sum($weekLoads);
                $avg = $sum / 7;
                $std = $this->standardDeviation($weekLoads);

                $this->trimpValues[$day] = $sum;
                $this->monotonyValues[$day] = $std > 0 ? $avg / $std : 0;
                $this->strainValues[$day] = $this->trimpValues[$day] * $this->monotonyValues[$day];
            } else {
                $this->trimpValues[$day] = null;
                $this->monotonyValues[$day] = null;
                $this->strainValues[$day] = null;
            }

            $this->acRatioValues[$day] = round($this->atlValues[$day] / $this->ctlValues[$day], 2);
        }

        // Round numbers when all calculating is done.
        $this->atlValues = array_map(fn (int|float $value) => round($value, 1), $this->atlValues);
        $this->ctlValues = array_map(fn (int|float $value) => round($value, 1), $this->ctlValues);
        $this->tsbValues = array_map(fn (int|float $value) => round($value, 1), $this->tsbValues);
        $this->trimpValues = array_map(fn (int|float|null $value) => null === $value ? null : (int) round($value), $this->trimpValues);
        $this->strainValues = array_map(fn (int|float|null $value) => null === $value ? null : (int) round($value), $this->strainValues);
        $this->monotonyValues = array_map(fn (int|float|null $value) => null === $value ? null : round($value, 2), $this->monotonyValues);
    }

    /**
     * @return array<int, int|float>
     */
    public function getAtlValues(): array
    {
        return $this->atlValues;
    }

    public function getCurrentAtl(): ?float
    {
        if (empty($this->atlValues)) {
            return null;
        }

        return end($this->atlValues);
    }

    /**
     * @return array<int, int|float>
     */
    public function getCtlValues(): array
    {
        return $this->ctlValues;
    }

    public function getCurrentCtl(): ?float
    {
        if (empty($this->ctlValues)) {
            return null;
        }

        return end($this->ctlValues);
    }

    /**
     * @return array<int, int|float>
     */
    public function getTsbValues(): array
    {
        return $this->tsbValues;
    }

    public function getCurrentTsb(): ?float
    {
        if (empty($this->tsbValues)) {
            return null;
        }

        return end($this->tsbValues);
    }

    /**
     * @return array<int, int|float|null>
     */
    public function getTrimpValues(): array
    {
        return $this->trimpValues;
    }

    public function getCurrentTrimp(): ?float
    {
        if (empty($this->trimpValues)) {
            return null;
        }

        return end($this->trimpValues);
    }

    public function getCurrentMonotony(): ?float
    {
        if (empty($this->monotonyValues)) {
            return null;
        }

        return end($this->monotonyValues);
    }

    public function getCurrentStrain(): ?float
    {
        if (empty($this->strainValues)) {
            return null;
        }

        return end($this->strainValues);
    }

    public function getCurrentAcRatio(): ?float
    {
        if (empty($this->acRatioValues)) {
            return null;
        }

        return end($this->acRatioValues);
    }

    /**
     * @param array<int, int|float> $values
     */
    private function standardDeviation(array $values): float
    {
        $mean = array_sum($values) / count($values);
        $sumSquares = 0;
        foreach ($values as $v) {
            $sumSquares += pow($v - $mean, 2);
        }

        return sqrt($sumSquares / count($values));
    }
}
