<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity\Training;

use App\Domain\Strava\Activity\Activity;
use App\Domain\Strava\Activity\ActivityType;
use App\Domain\Strava\Athlete\Athlete;

final readonly class TrainingMetricsCalculator
{
    private const int CTL_TIME_CONSTANT_IN_DAYS = 42;
    private const int ATL_TIME_CONSTANT_IN_DAYS = 7;

    private const float SCALING_LONG_CYCLES = 0.52;
    private const float SCALING_SHORT_CYCLES = 0.16;
    private const float SCALING_RUN_WITHOUT_HR = 0.36;
    private const float SCALING_RUN_WITH_HR = 0.54;
    private const float DEFAULT_ACTIVITY_SCALING_FACTOR = 1.0;

    private const int LONG_CYCLE_DURATION_THRESHOLD_MINUTES = 60;

    private const float HR_TRIMP_FACTOR = 1.0;
    private const float HR_TRIMP_EXPONENT = 1.67;

    private const float DEFAULT_INTENSITY = 0.4;

    private const float DEFAULT_TIME_DECAY_FACTOR = 1.0;
    private const array PACE_THRESHOLDS_INTENSITY_MAP = [
        3.5 => 0.85, // <= 3:30 min/km (elite level)
        4.0 => 0.75, // <= 4:00 min/km
        4.5 => 0.65, // <= 4:30 min/km
        5.0 => 0.60, // <= 5:00 min/km
        5.5 => 0.55, // <= 5:30 min/km
        6.0 => 0.50, // <= 6:00 min/km
        6.5 => 0.45, // <= 6:30 min/km
        7.0 => 0.40, // <= 7:00 min/km
        8.0 => 0.35, // <= 8:00 min/km
    ];
    private const float PACE_DEFAULT_INTENSITY = 0.3; // > 8:00 min/km
    private const array SPEED_THRESHOLDS_INTENSITY_MAP = [
        35 => 0.9, // >= 35 km/h
        30 => 0.8, // >= 30 km/h
        25 => 0.7, // >= 25 km/h
        20 => 0.6, // >= 20 km/h
    ];
    private const float SPEED_DEFAULT_INTENSITY = 0.5; // < 20 km/h

    public static function calculateMetrics(
        array $dailyLoadData,
        ?array $previousMetrics = null,
        ?string $startDate = null,
    ): array {
        ksort($dailyLoadData);

        $dates = array_keys($dailyLoadData);
        $metrics = [];

        if (null === $startDate && !empty($dates)) {
            $startDate = $dates[0];
        }

        $startingCtl = 0.0;
        $startingAtl = 0.0;
        if (null !== $previousMetrics && null !== $startDate) {
            $mostRecentDate = null;
            foreach ($previousMetrics as $date => $metric) {
                if ($date < $startDate && (null === $mostRecentDate || $date > $mostRecentDate)) {
                    $mostRecentDate = $date;
                    $startingCtl = $metric['ctl'] ?? 0.0;
                    $startingAtl = $metric['atl'] ?? 0.0;
                }
            }
        }

        $ctl = $startingCtl;
        $atl = $startingAtl;

        foreach ($dates as $date) {
            if (null !== $startDate && $date < $startDate) {
                continue;
            }

            $trimp = $dailyLoadData[$date]['trimp'];

            $timeDecayFactor = self::DEFAULT_TIME_DECAY_FACTOR;

            $ctlDecay = pow(exp(-1 / self::CTL_TIME_CONSTANT_IN_DAYS), $timeDecayFactor);
            $atlDecay = pow(exp(-1 / self::ATL_TIME_CONSTANT_IN_DAYS), $timeDecayFactor);
            $ctl = $ctl * $ctlDecay + $trimp * (1 - $ctlDecay);
            $atl = $atl * $atlDecay + $trimp * (1 - $atlDecay);

            $tsb = $ctl - $atl;
            $acRatio = $ctl > 0 ? $atl / $ctl : 0;

            $metrics[$date] = [
                'ctl' => $ctl,
                'atl' => $atl,
                'tsb' => $tsb,
                'acRatio' => $acRatio,
                'trimp' => $trimp,
            ];
        }

        if (empty($metrics)) {
            return [
                'currentCtl' => round($startingCtl, 1),
                'currentAtl' => round($startingAtl, 1),
                'currentTsb' => round($startingCtl - $startingAtl, 1),
                'acRatio' => round($startingCtl > 0 ? $startingAtl / $startingCtl : 0, 2),
                'restDaysLastWeek' => 7,
                'monotony' => 0.0,
                'strain' => 0.0,
                'weeklyTrimp' => 0.0,
                'dailyMetrics' => [],
            ];
        }

        $lastCalculatedDateStr = array_key_last($metrics);
        $lastCalculatedDate = new \DateTimeImmutable($lastCalculatedDateStr);

        $lastSevenDaysTrimp = [];
        $weeklyTrimp = 0;
        $restDaysLastWeek = 0;

        for ($i = 0; $i < 7; ++$i) {
            $checkDate = $lastCalculatedDate->modify("-{$i} days")->format('Y-m-d');
            $dailyTrimp = $metrics[$checkDate]['trimp'] ?? 0.0; // Default to 0 if date not in metrics

            $lastSevenDaysTrimp[] = $dailyTrimp;
            $weeklyTrimp += $dailyTrimp;

            if (0 == $dailyTrimp) {
                ++$restDaysLastWeek;
            }
        }

        $monotony = 0.0;
        $strain = 0.0;

        if (7 === count($lastSevenDaysTrimp)) {
            $avgDailyLoad = $weeklyTrimp / 7;

            if ($avgDailyLoad > 0) {
                $variance = 0.0;
                foreach ($lastSevenDaysTrimp as $trimpVal) {
                    $variance += pow($trimpVal - $avgDailyLoad, 2);
                }
                $stdDev = sqrt($variance / 7);
                $monotony = $stdDev > 0 ? $avgDailyLoad / $stdDev : 0;

                $strain = $weeklyTrimp * $monotony;
            }
        }

        $latestMetrics = end($metrics);

        return [
            'currentCtl' => round($latestMetrics['ctl'], 1),
            'currentAtl' => round($latestMetrics['atl'], 1),
            'currentTsb' => round($latestMetrics['tsb'], 1),
            'acRatio' => round($latestMetrics['acRatio'], 2),
            'restDaysLastWeek' => $restDaysLastWeek,
            'monotony' => round($monotony, 2),
            'strain' => round($strain, 0),
            'weeklyTrimp' => round($weeklyTrimp),
            'dailyMetrics' => $metrics,
        ];
    }

    public static function calculateTrimp(Activity $activity, Athlete $athlete): float
    {
        $durationMinutes = $activity->getMovingTimeInSeconds() / 60;
        if ($durationMinutes <= 0) {
            return 0.0;
        }

        $baseTrimp = 0.0;
        $dataSourceForScaling = 'duration';

        $activityType = $activity->getSportType()->getActivityType();
        $activityDate = $activity->getStartDate();
        $maxHr = $athlete->getMaxHeartRate($activityDate);
        $restingHr = 0;

        $calculatedSegmentTrimp = 0.0;
        if (in_array($activityType, [ActivityType::RUN, ActivityType::RIDE], true)) {
            $rawData = $activity->getRawData();
            $splits = $rawData['splits_metric'] ?? [];
            if (is_array($splits) && count($splits) > 0) {
                foreach ($splits as $split) {
                    $sec = $split['moving_time'] ?? $split['elapsed_time'] ?? 0;
                    $segmentDuration = $sec / 60;
                    $avgHrSplit = $split['average_heartrate'] ?? 0;

                    if ($avgHrSplit > 0 && $segmentDuration > 0) {
                        $hrRatio = 0.0;
                        if ($maxHr > $restingHr) {
                            $hrRatio = ($avgHrSplit - $restingHr) / ($maxHr - $restingHr);
                            $hrRatio = max(0.0, min(1.0, $hrRatio));
                        } elseif ($maxHr > 0) {
                            $hrRatio = max(0.0, min(1.0, $avgHrSplit / $maxHr));
                        }
                        $calculatedSegmentTrimp += self::calculateTrimpFromIntensity($segmentDuration, $hrRatio);
                    }
                }
                if ($calculatedSegmentTrimp > 0) {
                    $baseTrimp = $calculatedSegmentTrimp;
                    $dataSourceForScaling = 'hr';
                }
            }
        }

        if (0.0 == $baseTrimp) {
            $averageHr = $activity->getAverageHeartRate();
            if ($averageHr > 0) {
                $hrRatio = 0.0;
                if ($maxHr > $restingHr) {
                    $hrRatio = ($averageHr - $restingHr) / ($maxHr - $restingHr);
                    $hrRatio = max(0.0, min(1.0, $hrRatio));
                } elseif ($maxHr > 0) {
                    $hrRatio = max(0.0, min(1.0, $averageHr / $maxHr));
                }
                $baseTrimp = self::calculateTrimpFromIntensity($durationMinutes, $hrRatio);
                $dataSourceForScaling = 'hr';
            } else {
                $averageSpeed = $activity->getAverageSpeed()?->toFloat();

                if ($averageSpeed > 0) {
                    $dataSourceForScaling = 'pace_speed';
                    $baseTrimp = match ($activityType) {
                        ActivityType::RUN, ActivityType::WALK => self::calculatePaceBasedTrimp($durationMinutes, $averageSpeed),
                        ActivityType::RIDE => self::calculateSpeedBasedTrimp($durationMinutes, $averageSpeed),
                        default => self::calculateDurationBasedTrimp($durationMinutes),
                    };
                } else {
                    $baseTrimp = self::calculateDurationBasedTrimp($durationMinutes);
                    $dataSourceForScaling = 'duration';
                }
            }
        }

        $scalingFactor = self::DEFAULT_ACTIVITY_SCALING_FACTOR;

        if (ActivityType::RUN === $activityType || ActivityType::WALK === $activityType) {
            if ('hr' === $dataSourceForScaling) {
                $scalingFactor = self::SCALING_RUN_WITH_HR;
            } else {
                $scalingFactor = self::SCALING_RUN_WITHOUT_HR;
            }
        } elseif (ActivityType::RIDE === $activityType) {
            if ($durationMinutes > self::LONG_CYCLE_DURATION_THRESHOLD_MINUTES) {
                $scalingFactor = self::SCALING_LONG_CYCLES;
            } else {
                $scalingFactor = self::SCALING_SHORT_CYCLES;
            }
        }

        return $baseTrimp * $scalingFactor;
    }

    private static function calculateTrimpFromIntensity(float $durationMinutes, float $intensity): float
    {
        $intensity = max(0.0, $intensity);

        $baseTrimp = $durationMinutes * $intensity * self::HR_TRIMP_FACTOR * exp(self::HR_TRIMP_EXPONENT * $intensity);

        $decayFactor = $durationMinutes > 60
            ? 1 - (($durationMinutes - 60) * 0.005)
            : 1;

        return $baseTrimp * max(0.7, $decayFactor);
    }

    private static function calculatePaceBasedTrimp(float $durationMinutes, float $averageSpeedMps): float
    {
        $paceMinPerKm = ($averageSpeedMps > 0) ? (1000 / $averageSpeedMps) / 60 : INF;
        $intensity = self::PACE_DEFAULT_INTENSITY;

        $paceMap = self::PACE_THRESHOLDS_INTENSITY_MAP;
        ksort($paceMap);

        foreach ($paceMap as $threshold => $intensityValue) {
            if ($paceMinPerKm <= $threshold) {
                $intensity = $intensityValue;
                break;
            }
        }

        return self::calculateTrimpFromIntensity($durationMinutes, $intensity);
    }

    private static function calculateSpeedBasedTrimp(float $durationMinutes, float $averageSpeedMps): float
    {
        $speedKmh = $averageSpeedMps * 3.6;
        $intensity = self::SPEED_DEFAULT_INTENSITY;

        $speedMap = self::SPEED_THRESHOLDS_INTENSITY_MAP;
        krsort($speedMap);

        foreach ($speedMap as $threshold => $intensityValue) {
            if ($speedKmh >= $threshold) {
                $intensity = $intensityValue;
                break;
            }
        }

        return self::calculateTrimpFromIntensity($durationMinutes, $intensity);
    }

    private static function calculateDurationBasedTrimp(float $durationMinutes): float
    {
        return self::calculateTrimpFromIntensity($durationMinutes, self::DEFAULT_INTENSITY);
    }
}
