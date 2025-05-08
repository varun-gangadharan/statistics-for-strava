<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity\Training;

final readonly class TrainingMetrics
{
    private function __construct(
        private array $intensities,
    ) {
    }

    public static function create(array $intensities): TrainingMetrics
    {
        return new self($intensities);
    }

    public function getMetrics(int $atlTau = 7, int $ctlTau = 42): array
    {
        $alphaATL = 1 - exp(-1 / $atlTau);
        $alphaCTL = 1 - exp(-1 / $ctlTau);

        $atl = [];
        $ctl = [];
        $tsb = [];

        $weeklyTRIMP = [];
        $monotony = [];
        $strain = [];
        $acRatio = [];

        foreach ($this->intensities as $day => $load) {
            // ATL & CTL
            if (0 === $day) {
                $atl[$day] = $load;
                $ctl[$day] = $load;
                $tsb[$day] = 0;
            } else {
                $atl[$day] = ($load * $alphaATL) + ($atl[$day - 1] * (1 - $alphaATL));
                $ctl[$day] = ($load * $alphaCTL) + ($ctl[$day - 1] * (1 - $alphaCTL));
                $tsb[$day] = $ctl[$day - 1] - $atl[$day - 1];
            }

            if ($day >= 6) { // Day 6 = first full week
                $weekLoads = array_slice($this->intensities, $day - 6, 7);
                $sum = array_sum($weekLoads);
                $avg = $sum / 7;
                $std = $this->standardDeviation($weekLoads);

                $weeklyTRIMP[$day] = $sum;
                $monotony[$day] = $std > 0 ? $avg / $std : 0;
                $strain[$day] = $weeklyTRIMP[$day] * $monotony[$day];
            } else {
                $weeklyTRIMP[$day] = null;
                $monotony[$day] = null;
                $strain[$day] = null;
            }

            $acRatio[$day] = $atl[$day] / $ctl[$day];
        }

        return [
            'ATL' => $atl,
            'CTL' => $ctl,
            'TSB' => $tsb,
            'TRIMP' => $weeklyTRIMP,
            'Monotony' => $monotony,
            'Strain' => $strain,
            'acRatio' => $acRatio,
        ];
    }

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
