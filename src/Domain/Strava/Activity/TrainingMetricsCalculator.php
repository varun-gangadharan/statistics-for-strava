<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Domain\Strava\Athlete\Athlete;

// Added for potential date errors

/**
 * Shared service to calculate training metrics from activity data
 * Used by both dashboard and training metrics pages to ensure consistency.
 */
final readonly class TrainingMetricsCalculator
{
    // == Constants ==

    // Time constants for CTL/ATL decay
    private const CTL_TIME_CONSTANT = 42; // 42-day time constant for Chronic Training Load
    private const ATL_TIME_CONSTANT = 7;  // 7-day time constant for Acute Training Load

    // Global scaling factor for TRIMP calculations
    // Evidence-based population scaling
    private const GLOBAL_SCALING_FACTOR = 0.7875;

    // TRIMP calculation factors
    private const HR_TRIMP_FACTOR = 1.0;     // Standard HR-based factor
    private const HR_TRIMP_EXPONENT = 1.67;   // Standard HR-based exponent

    // Default intensity if HR/Pace/Speed is unavailable
    private const DEFAULT_INTENSITY = 0.4;

    private const DEFAULT_TIME_DECAY_FACTOR = 1.0;

    // Intensity mapping based on Pace (Run/Walk) - Threshold => Intensity
    // Assumes pace is in minutes per kilometer. Lower pace value means faster.
    // Updated pace thresholds with more granular categories and adjusted intensity values
    private const PACE_THRESHOLDS_INTENSITY_MAP = [
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
    private const PACE_DEFAULT_INTENSITY = 0.3; // > 8:00 min/km

    // Intensity mapping based on Speed (Ride) - Threshold => Intensity
    // Assumes speed is in kilometers per hour. Higher speed value means faster.
    private const SPEED_THRESHOLDS_INTENSITY_MAP = [
        35 => 0.9, // >= 35 km/h
        30 => 0.8, // >= 30 km/h
        25 => 0.7, // >= 25 km/h
        20 => 0.6, // >= 20 km/h
    ];
    private const SPEED_DEFAULT_INTENSITY = 0.5; // < 20 km/h

    // == Core Metrics Calculation ==

    /**
     * Calculates all training metrics from daily load data.
     *
     * @param array<string, array{trimp: float, duration: int, intensity: float}>           $dailyLoadData   Daily load keyed by date ('YYYY-MM-DD')
     * @param array<string, array{ctl: float, atl: float, tsb: float, acRatio: float}>|null $previousMetrics Historical metrics keyed by date ('YYYY-MM-DD')
     * @param string|null                                                                   $startDate       Optional date ('YYYY-MM-DD') to start calculations from
     *
     * @return array<string, mixed> Training metrics summary and daily breakdown
     *
     * @throws \Exception If date operations fail
     */
    public static function calculateMetrics(
        array $dailyLoadData,
        ?array $previousMetrics = null,
        ?string $startDate = null,
    ): array {
        // Ensure data is sorted chronologically by date key
        ksort($dailyLoadData);

        $dates = array_keys($dailyLoadData);
        $metrics = [];

        // Determine starting date if not provided
        if (null === $startDate && !empty($dates)) {
            $startDate = $dates[0];
        }

        // Determine starting CTL/ATL values from historical metrics before the start date
        $startingCtl = 0.0;
        $startingAtl = 0.0;
        $lastMetricDate = null;
        if (null !== $previousMetrics && null !== $startDate) {
            $mostRecentDate = null;
            foreach ($previousMetrics as $date => $metric) {
                // Consider metrics strictly *before* the start date to initialize
                // If the start date itself has previous metrics, they will be overwritten by calculation
                if ($date < $startDate && (null === $mostRecentDate || $date > $mostRecentDate)) {
                    $mostRecentDate = $date;
                    $startingCtl = $metric['ctl'] ?? 0.0;
                    $startingAtl = $metric['atl'] ?? 0.0;
                    $lastMetricDate = $date;
                }
            }
        }

        $ctl = $startingCtl;
        $atl = $startingAtl;
        $prevDate = $lastMetricDate;

        // Process each day sequentially, updating metrics daily
        foreach ($dates as $date) {
            // Skip dates before the specified start date if provided
            if (null !== $startDate && $date < $startDate) {
                continue;
            }

            $trimp = $dailyLoadData[$date]['trimp'];

            // Apply continuous-time Exponential Moving Average (EMA) decay with time adjustment:
            $timeDecayFactor = self::DEFAULT_TIME_DECAY_FACTOR;
            $ctlDecay = pow(exp(-1 / self::CTL_TIME_CONSTANT), $timeDecayFactor);
            $atlDecay = pow(exp(-1 / self::ATL_TIME_CONSTANT), $timeDecayFactor);
            $ctl = $ctl * $ctlDecay + $trimp * (1 - $ctlDecay);
            $atl = $atl * $atlDecay + $trimp * (1 - $atlDecay);

            // Calculate derived metrics
            $tsb = $ctl - $atl; // Training Stress Balance (Form)
            $acRatio = $ctl > 0 ? $atl / $ctl : 0; // Acute:Chronic Workload Ratio

            // Store daily metrics
            $metrics[$date] = [
                'ctl' => $ctl,
                'atl' => $atl,
                'tsb' => $tsb,
                'acRatio' => $acRatio,
                'trimp' => $trimp, // Store the TRIMP used for this day's calculation
            ];
        }

        // If no metrics were calculated (e.g., empty $dailyLoadData or all dates before $startDate)
        if (empty($metrics)) {
            return [
                'currentCtl' => round($startingCtl, 1), // Return initial values
                'currentAtl' => round($startingAtl, 1),
                'currentTsb' => round($startingCtl - $startingAtl, 1),
                'acRatio' => round($startingCtl > 0 ? $startingAtl / $startingCtl : 0, 2),
                'restDaysLastWeek' => 7, // Assume all rest if no data
                'monotony' => 0.0,
                'strain' => 0.0,
                'weeklyTrimp' => 0.0,
                'dailyMetrics' => [],
            ];
        }

        // == Calculate Weekly Metrics (Monotony, Strain, etc.) ==
        // Based on the last 7 *calendar* days ending on the most recent date in the calculated metrics

        $lastCalculatedDateStr = array_key_last($metrics);
        $lastCalculatedDate = new \DateTimeImmutable($lastCalculatedDateStr); // Use DateTime for reliable date math

        $lastSevenDaysTrimp = [];
        $weeklyTrimp = 0;
        $restDaysLastWeek = 0;

        // Iterate through the last 7 calendar days ending on the last calculated date
        for ($i = 0; $i < 7; ++$i) {
            $checkDate = $lastCalculatedDate->modify("-{$i} days")->format('Y-m-d');

            // Use the TRIMP value from the *calculated metrics* for consistency
            // If a day is missing in the calculated metrics (e.g., before start date or no activity), its TRIMP is effectively 0 for this weekly calculation
            $dailyTrimp = $metrics[$checkDate]['trimp'] ?? 0.0; // Default to 0 if date not in metrics

            $lastSevenDaysTrimp[] = $dailyTrimp; // Store for standard deviation calculation
            $weeklyTrimp += $dailyTrimp;

            if (0 == $dailyTrimp) {
                ++$restDaysLastWeek;
            }
        }

        // Calculate Monotony and Strain
        $monotony = 0.0;
        $strain = 0.0;

        if (7 === count($lastSevenDaysTrimp)) { // Ensure we have exactly 7 days
            $avgDailyLoad = $weeklyTrimp / 7;

            if ($avgDailyLoad > 0) {
                // Calculate Standard Deviation of the last 7 days' TRIMP
                $variance = 0.0;
                foreach ($lastSevenDaysTrimp as $trimp) {
                    $variance += pow($trimp - $avgDailyLoad, 2);
                }
                $stdDev = sqrt($variance / 7);

                // Monotony = Average Daily Load / Standard Deviation
                // Avoid division by zero if all days had the same TRIMP (stdDev = 0)
                $monotony = $stdDev > 0 ? $avgDailyLoad / $stdDev : 0;

                // Strain = Weekly Total Load * Monotony
                $strain = $weeklyTrimp * $monotony;
            }
            // If avgDailyLoad is 0, monotony and strain remain 0
        }

        // Get the most recent calculated metrics
        $latestMetrics = end($metrics); // Already points to the last element after the loop

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
            'dailyMetrics' => $metrics, // Include the full daily breakdown
        ];
    }

    // == TRIMP Calculation Helpers ==

    /**
     * Calculates TRIMP for a single activity based on available data (HR, Pace, Speed, or Duration).
     *
     * @param mixed   $activity An object representing the activity (needs methods like getMovingTimeInSeconds, getAverageHeartRate, etc.)
     * @param Athlete $athlete  The athlete performing the activity (used for Max HR)
     *
     * @return float The calculated TRIMP value
     */
    public static function calculateTrimp($activity, Athlete $athlete): float
    {
        $durationMinutes = $activity->getMovingTimeInSeconds() / 60;
        if ($durationMinutes <= 0) {
            return 0.0; // No TRIMP for zero duration activities
        }

        // 0. Segment-based TRIMP for runs and rides using per-kilometer splits if available
        $activityType = $activity->getSportType()->getActivityType();
        if (in_array($activityType, [ActivityType::RUN, ActivityType::RIDE], true) && method_exists($activity, 'getRawData')) {
            $rawData = $activity->getRawData();
            $splits = $rawData['splits_metric'] ?? [];
            if (is_array($splits) && count($splits) > 0) {
                $segmentTrimp = 0.0;
                $activityDate = $activity->getStartDate();
                $maxHr = $athlete->getMaxHeartRate($activityDate);
                $restingHr = $athlete->getRestingHeartRate();
                foreach ($splits as $split) {
                    $sec = $split['moving_time'] ?? $split['elapsed_time'] ?? 0;
                    $segmentDuration = $sec / 60;
                    $avgHrSplit = $split['average_heartrate'] ?? 0;
                    if ($avgHrSplit > 0 && $segmentDuration > 0) {
                        if ($maxHr > $restingHr) {
                            $hrRatio = ($avgHrSplit - $restingHr) / ($maxHr - $restingHr);
                            $hrRatio = max(0.0, min(1.0, $hrRatio));
                        } elseif ($maxHr > 0) {
                            $hrRatio = max(0.0, min(1.0, $avgHrSplit / $maxHr));
                        } else {
                            $hrRatio = 0.0;
                        }
                        $segmentTrimp += self::calculateTrimpFromIntensity($segmentDuration, $hrRatio);
                    }
                }
                // Only use segment-based TRIMP if HR data was present in splits
                if ($segmentTrimp > 0) {
                    $scaledSegmentTrimp = $segmentTrimp * self::GLOBAL_SCALING_FACTOR;

                    return $scaledSegmentTrimp;
                }
            }
        }

        $trimp = 0.0;

        // 1. Priority: Heart Rate based TRIMP
        $averageHr = $activity->getAverageHeartRate(); // Assuming this returns float|null
        if ($averageHr > 0) {
            $activityDate = $activity->getStartDate(); // Assuming returns DateTimeInterface or similar
            $maxHr = $athlete->getMaxHeartRate($activityDate); // Assuming returns float > 0

            $restingHr = $athlete->getRestingHeartRate();
            if ($maxHr > $restingHr) {
                // Implement HR Reserve calculation
                $hrRatio = ($averageHr - $restingHr) / ($maxHr - $restingHr);
                // Clamp hrRatio between 0 and 1
                $hrRatio = max(0.0, min(1.0, $hrRatio));
            } elseif ($maxHr > 0) {
                // Fallback to simple HR ratio if resting HR unavailable or invalid
                $hrRatio = max(0.0, min(1.0, $averageHr / $maxHr));
            } else {
                // No valid HR data
                $hrRatio = 0.0;
            }
            $trimp = self::calculateTrimpFromIntensity($durationMinutes, $hrRatio);
        } else {
            // 2. Fallback: Pace/Speed based TRIMP (if HR unavailable)
            $activityType = $activity->getSportType()->getActivityType(); // Assuming returns enum or string constant
            $averageSpeed = $activity->getAverageSpeed()?->toFloat(); // Assuming returns Speed object or null

            if ($averageSpeed > 0) {
                $trimp = match ($activityType) {
                    ActivityType::RUN, ActivityType::WALK => self::calculatePaceBasedTrimp($durationMinutes, $averageSpeed),
                    ActivityType::RIDE => self::calculateSpeedBasedTrimp($durationMinutes, $averageSpeed),
                    default => self::calculateDurationBasedTrimp($durationMinutes), // Fallback for other types with speed
                };
            } else {
                // 3. Fallback: Duration-based TRIMP (lowest priority)
                $trimp = self::calculateDurationBasedTrimp($durationMinutes);
            }
        }

        // Apply global scaling factor to all TRIMP calculations
        return $trimp * self::GLOBAL_SCALING_FACTOR;
    }

    /**
     * Helper to calculate TRIMP from duration and intensity.
     * This is the core Banister's TRIMP formula implementation.
     * TRIMP = Duration * Intensity * Factor * e^(Exponent * Intensity).
     *
     * @param float $durationMinutes activity duration in minutes
     * @param float $intensity       Relative intensity (e.g., HR ratio, pace/speed based).
     *
     * @return float calculated TRIMP
     */
    private static function calculateTrimpFromIntensity(float $durationMinutes, float $intensity): float
    {
        // Ensure intensity is not negative, though it should ideally be clamped earlier
        $intensity = max(0.0, $intensity);

        // Calculate base TRIMP with Banister's formula
        $baseTrimp = $durationMinutes * $intensity * self::HR_TRIMP_FACTOR * exp(self::HR_TRIMP_EXPONENT * $intensity);

        // Apply duration decay for longer activities (>60 minutes)
        // This addresses TRIMP overestimation for long low-intensity activities
        // Formula: linear decay of 0.5% per minute beyond 60 minutes, with 70% minimum
        $decayFactor = $durationMinutes > 60
            ? 1 - (($durationMinutes - 60) * 0.005)
            : 1;

        // Apply decay with minimum threshold of 70%
        return $baseTrimp * max(0.7, $decayFactor);
    }

    /**
     * Calculates TRIMP based on pace intensity for Run/Walk activities.
     *
     * @param float $durationMinutes duration in minutes
     * @param float $averageSpeedMps average speed in meters per second
     *
     * @return float calculated TRIMP
     */
    private static function calculatePaceBasedTrimp(float $durationMinutes, float $averageSpeedMps): float
    {
        // Convert speed (m/s) to pace (min/km)
        $paceMinPerKm = ($averageSpeedMps > 0) ? (1000 / $averageSpeedMps) / 60 : INF;
        $intensity = self::PACE_DEFAULT_INTENSITY; // Start with the default (slowest)

        // Create a mutable copy of the constant map because ksort modifies the array in place.
        $paceMap = self::PACE_THRESHOLDS_INTENSITY_MAP;
        // Sort the *copy* by key (pace threshold) in ascending order (fastest pace first)
        ksort($paceMap);

        // Find the highest intensity bucket the pace falls into
        // Iterate thresholds from fastest (lowest pace value) to slowest using the sorted copy
        foreach ($paceMap as $threshold => $intensityValue) {
            if ($paceMinPerKm <= $threshold) {
                $intensity = $intensityValue;
                break; // Found the correct intensity bracket
            }
        }

        return self::calculateTrimpFromIntensity($durationMinutes, $intensity);
    }

    /**
     * Calculates TRIMP based on speed intensity for Ride activities.
     *
     * @param float $durationMinutes duration in minutes
     * @param float $averageSpeedMps average speed in meters per second
     *
     * @return float calculated TRIMP
     */
    private static function calculateSpeedBasedTrimp(float $durationMinutes, float $averageSpeedMps): float
    {
        // Convert speed (m/s) to speed (km/h)
        $speedKmh = $averageSpeedMps * 3.6;
        $intensity = self::SPEED_DEFAULT_INTENSITY; // Start with the default (slowest)

        // Create a mutable copy of the constant map because krsort modifies the array in place.
        $speedMap = self::SPEED_THRESHOLDS_INTENSITY_MAP;
        // Sort the *copy* by key (speed threshold) in reverse order (fastest speed first)
        krsort($speedMap); // Fixed: Sort the copy, not the constant

        // Find the highest intensity bucket the speed falls into
        // Iterate thresholds from fastest (highest speed value) to slowest using the sorted copy
        foreach ($speedMap as $threshold => $intensityValue) {
            if ($speedKmh >= $threshold) {
                $intensity = $intensityValue;
                break; // Found the correct intensity bracket
            }
        }

        return self::calculateTrimpFromIntensity($durationMinutes, $intensity);
    }

    /**
     * Calculates TRIMP using a default intensity, based only on duration.
     * Used as a fallback when HR, pace, or speed data is insufficient.
     *
     * @param float $durationMinutes duration in minutes
     *
     * @return float calculated TRIMP
     */
    private static function calculateDurationBasedTrimp(float $durationMinutes): float
    {
        return self::calculateTrimpFromIntensity($durationMinutes, self::DEFAULT_INTENSITY);
    }
}
