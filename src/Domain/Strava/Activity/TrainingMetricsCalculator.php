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
    /**
     * Calculates all training metrics from daily load data.
     *
     * @param array<string, array{trimp: float, duration: int, intensity: float}> $dailyLoadData
     *
     * @return array Training metrics
     */
    public static function calculateMetrics(array $dailyLoadData): array
    {
        $ctl = 0.0;
        $atl = 0.0;
        $ctlDecay = 2 / (42 + 1);  // Proper 42-day EMA decay factor
        $atlDecay = 2 / (7 + 1);   // Proper 7-day EMA decay factor

        // Ensure data is sorted chronologically
        ksort($dailyLoadData);

        foreach ($dailyLoadData as $data) {
            $trimp = $data['trimp'];
            $ctl = $trimp * $ctlDecay + $ctl * (1 - $ctlDecay);
            $atl = $trimp * $atlDecay + $atl * (1 - $atlDecay);
        }

        // Calculate additional metrics
        $dates = array_keys($dailyLoadData);
        $lastSevenDays = array_slice($dates, -7, 7, true);
        $weeklyTrimp = 0;
        $restDaysLastWeek = 0;

        foreach ($lastSevenDays as $date) {
            $weeklyTrimp += $dailyLoadData[$date]['trimp'];
            if (0 == $dailyLoadData[$date]['trimp']) {
                ++$restDaysLastWeek;
            }
        }

        // Calculate monotony and strain
        $monotony = 0;
        $strain = 0;
        if (count($lastSevenDays) > 0) {
            $dailyTrimps = array_column(array_intersect_key($dailyLoadData, array_flip($lastSevenDays)), 'trimp');
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
        }

        $tsb = $ctl - $atl; // Training Stress Balance
        $acRatio = $ctl > 0 ? $atl / $ctl : 0; // Acute:Chronic ratio

        return [
            'currentCtl' => round($ctl, 1),
            'currentAtl' => round($atl, 1),
            'currentTsb' => round($tsb, 1),
            'acRatio' => round($acRatio, 2),
            'restDaysLastWeek' => $restDaysLastWeek,
            'monotony' => round($monotony, 2),
            'strain' => round($strain, 0),
            'weeklyTrimp' => round($weeklyTrimp, 0),
        ];
    }

    /**
     * Calculates TRIMP for an activity.
     */
    public static function calculateTrimp($activity, Athlete $athlete): float
    {
        if ($activity->getAverageHeartRate()) {
            $activityDate = $activity->getStartDate();
            $maxHr = $athlete->getMaxHeartRate($activityDate);
            $intensity = $activity->getAverageHeartRate() / $maxHr;

            return ($activity->getMovingTimeInSeconds() / 60) * $intensity * 1.92 * exp(1.67 * $intensity);
        }

        if ($activity->getAverageSpeed()?->toFloat() > 0) {
            $activityType = $activity->getSportType()->getActivityType();

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
        $pace = 60 / $activity->getAverageSpeed()->toFloat();
        $intensity = min(1.0, max(0.5, $pace / 8));

        return ($activity->getMovingTimeInSeconds() / 60) * $intensity * 1.5;
    }

    private static function calculateSpeedBasedTrimp($activity): float
    {
        $speed = $activity->getAverageSpeed()->toFloat() * 3.6;
        $intensity = min(1.0, max(0.5, $speed / 40));

        return ($activity->getDistance()->toFloat() / 1000) * $speed * 0.05;
    }

    private static function calculateDurationBasedTrimp($activity): float
    {
        return ($activity->getMovingTimeInSeconds() / 60) * 0.6;
    }
}
