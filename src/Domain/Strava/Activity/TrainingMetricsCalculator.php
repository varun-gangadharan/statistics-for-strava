<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Domain\Strava\Athlete\Athlete;

/**
 * Shared service to calculate training metrics from activity data
 * Used by both dashboard and training metrics pages to ensure consistency
 */
final readonly class TrainingMetricsCalculator
{
    /**
     * Calculates all training metrics from daily load data
     * 
     * @param array<string, array{trimp: float, duration: int, intensity: float}> $dailyLoadData
     * @param array<string, array{ctl: float, atl: float, tsb: float, acRatio: float}|null> $previousMetrics Previous day's metrics (optional)
     * @return array Training metrics
     */
    public static function calculateMetrics(array $dailyLoadData, ?array $previousMetrics = null): array
    {
        // Continuous-time decay factors for CTL (42-day) and ATL (7-day)
        $ctlDecay = exp(-1 / 42);  // CTL decay λ = e^(−1/42)
        $atlDecay = exp(-1 / 7);   // ATL decay λ = e^(−1/7)
        
        // Ensure data is sorted chronologically
        ksort($dailyLoadData);
        
        $dates = array_keys($dailyLoadData);
        $metrics = [];
        
        // Initialize with previous metrics if provided, otherwise start with zeros
        $lastCtl = $previousMetrics[$dates[0] ?? ''] ?? ['ctl' => 0.0, 'atl' => 0.0, 'tsb' => 0.0, 'acRatio' => 0.0];
        $ctl = $lastCtl['ctl'] ?? 0.0;
        $atl = $lastCtl['atl'] ?? 0.0;
        
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
                'trimp' => $trimp
            ];
        }
        
        // Calculate weekly metrics from the last 7 days
        $lastDate = end($dates);
        $lastSevenDays = [];
        $currentDate = $lastDate;
        
        // Get the last 7 days, even if some are missing from the input data
        for ($i = 0; $i < 7; $i++) {
            $checkDate = date('Y-m-d', strtotime($currentDate . " - $i days"));
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
            if ($dayData['trimp'] == 0) {
                $restDaysLastWeek++;
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
            'dailyMetrics' => $metrics, // Include all daily metrics for historical tracking
        ];
    }

    /**
     * Calculates TRIMP for an activity
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
