<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Domain\Strava\Athlete\Athlete;

/**
 * Shared service to calculate training metrics from activity data
 * Used by both dashboard and training metrics pages to ensure consistency.
 */
final readonly class TrainingMetricsCalculator
{
    // Constants for TRIMP calculations
    private const HR_TRIMP_FACTOR = 1.92;     // Standard HR-based factor
    private const HR_TRIMP_EXPONENT = 1.67;   // Standard HR-based exponent

    /**
     * Calculates all training metrics from daily load data.
     *
     * @param array<string, array{trimp: float, duration: int, intensity: float}>           $dailyLoadData
     * @param array<string, array{ctl: float, atl: float, tsb: float, acRatio: float}|null> $previousMetrics Historical metrics keyed by date
     * @param string|null                                                                  $startDate Optional date to start calculations from
     *
     * @return array Training metrics
     */
    public static function calculateMetrics(
        array $dailyLoadData, 
        ?array $previousMetrics = null, 
        ?string $startDate = null
    ): array {
        // Continuous-time decay factors for CTL (42-day) and ATL (7-day)
        $ctlDecay = exp(-1 / 42);  // CTL decay λ = e^(−1/42)
        $atlDecay = exp(-1 / 7);   // ATL decay λ = e^(−1/7)

        // Ensure data is sorted chronologically
        ksort($dailyLoadData);

        $dates = array_keys($dailyLoadData);
        $metrics = [];

        // Determine starting values from historical metrics
        if ($startDate === null && !empty($dates)) {
            $startDate = $dates[0];
        }

        // Find most recent historical metrics before the start date
        $startingCtl = 0.0;
        $startingAtl = 0.0;
        
        if ($previousMetrics !== null && $startDate !== null) {
            $mostRecentDate = null;
            
            foreach ($previousMetrics as $date => $metric) {
                // Include metrics up to and including the start date
                if ($date <= $startDate && ($mostRecentDate === null || $date > $mostRecentDate)) {
                    $mostRecentDate = $date;
                    $startingCtl = $metric['ctl'] ?? 0.0;
                    $startingAtl = $metric['atl'] ?? 0.0;
                }
            }
        }

        $ctl = $startingCtl;
        error_log('starting ctl: ' . $ctl);
        $atl = $startingAtl;
        error_log('starting atl: ' . $atl);

        // Process each day sequentially, updating metrics daily
        foreach ($dates as $date) {
            $trimp = $dailyLoadData[$date]['trimp'];

            // Apply continuous-time EMA decay daily: new = prev*λ + trimp*(1−λ)
            $ctl = $ctl * $ctlDecay + $trimp * (1 - $ctlDecay);
            $atl = $atl * $atlDecay + $trimp * (1 - $atlDecay);

            // Calculate derived metrics
            $tsb = $ctl - $atl;
            $acRatio = $ctl > 0 ? $atl / $ctl : 0;

            // Store daily metrics
            $metrics[$date] = [
                'ctl' => $ctl,
                'atl' => $atl,
                'tsb' => $tsb,
                'acRatio' => $acRatio,
                'trimp' => $trimp,
            ];
        }

        // Calculate weekly metrics from the last 7 days
        $lastDate = end($dates);
        $lastSevenDays = [];
        $currentDate = $lastDate;

        // Get the last 7 days, even if some are missing from the input data
        for ($i = 0; $i < 7; ++$i) {
            $checkDate = date('Y-m-d', strtotime($currentDate." - $i days"));
            if (isset($dailyLoadData[$checkDate])) {
                $lastSevenDays[$checkDate] = $dailyLoadData[$checkDate];
            } else {
                // If a day doesn't exist in the input, assume zero
                $lastSevenDays[$checkDate] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
            }
        }

        $weeklyTrimp = 0;
        $restDaysLastWeek = 0;

        foreach ($lastSevenDays as $dayData) {
            $weeklyTrimp += $dayData['trimp'];
            if (0 == $dayData['trimp']) {
                ++$restDaysLastWeek;
            }
        }

        // Calculate monotony and strain
        $monotony = 0;
        $strain = 0;
        $dailyTrimps = array_column($lastSevenDays, 'trimp');
        $avgDailyLoad = array_sum($dailyTrimps) / 7;

        if ($avgDailyLoad > 0) {
            $variance = 0;
            foreach ($dailyTrimps as $trimp) {
                $variance += pow($trimp - $avgDailyLoad, 2);
            }
            $stdDev = sqrt($variance / 7);
            $monotony = $stdDev > 0 ? $avgDailyLoad / $stdDev : 0;
            $strain = $weeklyTrimp * $monotony;
        }

        // Get the most recent metrics
        $latestMetrics = $metrics[$lastDate] ?? end($metrics);

        // Return the final metrics summary
        return [
            'currentCtl' => round($latestMetrics['ctl'], 1),
            'currentAtl' => round($latestMetrics['atl'], 1),
            'currentTsb' => round($latestMetrics['tsb'], 1),
            'acRatio' => round($latestMetrics['acRatio'], 2),
            'restDaysLastWeek' => $restDaysLastWeek,
            'monotony' => round($monotony, 2),
            'strain' => round($strain, 0),
            'weeklyTrimp' => round($weeklyTrimp, 0),
            'dailyMetrics' => $metrics,
        ];
    }

    /**
     * Calculates TRIMP for an activity.
     */
    public static function calculateTrimp($activity, Athlete $athlete): float
    {
        $durationMinutes = $activity->getMovingTimeInSeconds() / 60;

        if ($activity->getAverageHeartRate()) {
            $activityDate = $activity->getStartDate();
            $maxHr = $athlete->getMaxHeartRate($activityDate);
            $intensity = $activity->getAverageHeartRate() / $maxHr;

            return $durationMinutes * $intensity * self::HR_TRIMP_FACTOR * exp(self::HR_TRIMP_EXPONENT * $intensity);
        }

        $activityType = $activity->getSportType()->getActivityType();

        if ($activity->getAverageSpeed()?->toFloat() > 0) {
            return match ($activityType) {
                ActivityType::RUN, ActivityType::WALK => self::calculatePaceBasedTrimp($activity),
                ActivityType::RIDE => self::calculateSpeedBasedTrimp($activity),
                default => self::calculateDurationBasedTrimp($activity),
            };
        }

        return self::calculateDurationBasedTrimp($activity);
    }

    private static function calculatePaceBasedTrimp($activity): float
    {
        $durationMinutes = $activity->getMovingTimeInSeconds() / 60;
        $pace = 60 / $activity->getAverageSpeed()->toFloat();

        if ($pace <= 4.0) {
            $intensity = 0.9;
        } elseif ($pace <= 5.0) {
            $intensity = 0.8;
        } elseif ($pace <= 6.0) {
            $intensity = 0.7;
        } elseif ($pace <= 7.0) {
            $intensity = 0.6;
        } else {
            $intensity = 0.5;
        }

        return $durationMinutes * $intensity * self::HR_TRIMP_FACTOR * exp(self::HR_TRIMP_EXPONENT * $intensity);
    }

    private static function calculateSpeedBasedTrimp($activity): float
    {
        $durationMinutes = $activity->getMovingTimeInSeconds() / 60;
        $speed = $activity->getAverageSpeed()->toFloat() * 3.6;

        if ($speed >= 35) {
            $intensity = 0.9;
        } elseif ($speed >= 30) {
            $intensity = 0.8;
        } elseif ($speed >= 25) {
            $intensity = 0.7;
        } elseif ($speed >= 20) {
            $intensity = 0.6;
        } else {
            $intensity = 0.5;
        }

        return $durationMinutes * $intensity * self::HR_TRIMP_FACTOR * exp(self::HR_TRIMP_EXPONENT * $intensity);
    }

    private static function calculateDurationBasedTrimp($activity): float
    {
        $durationMinutes = $activity->getMovingTimeInSeconds() / 60;
        $intensity = 0.6; // generic default intensity

        return $durationMinutes * $intensity * self::HR_TRIMP_FACTOR * exp(self::HR_TRIMP_EXPONENT * $intensity);
    }
}
