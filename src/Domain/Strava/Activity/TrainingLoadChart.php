<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class TrainingLoadChart
{
    /**
     * @param array<string, array{trimp: float, duration: int, intensity: float}> $dailyLoadData
     */
    private function __construct(
        private array $dailyLoadData,
        private int $ctlDays = 42, // ~6 weeks for Chronic Training Load
        private int $atlDays = 7,  // 7 days for Acute Training Load
    ) {
    }

    /**
     * @param array<string, array{trimp: float, duration: int, intensity: float}> $dailyLoadData
     */
    public static function fromDailyLoadData(
        array $dailyLoadData,
        int $ctlDays = 42,
        int $atlDays = 7,
    ): self {
        return new self(
            dailyLoadData: $dailyLoadData,
            ctlDays: $ctlDays,
            atlDays: $atlDays,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(bool $showFutureProjection = false): array
    {
        if (count($this->dailyLoadData) < 7) {
            return [];
        }

        // Sort data by date
        $dates = array_keys($this->dailyLoadData);
        sort($dates);

        // If showing 4 months history + 1 month future projection
        if ($showFutureProjection) {
            // Get the date range to display (last 4 months)
            $lastDate = end($dates);
            $lastDateObj = SerializableDateTime::fromString($lastDate);
            $fourMonthsAgo = clone $lastDateObj;
            $fourMonthsAgo->modify('-4 months');
            $fourMonthsAgoStr = $fourMonthsAgo->format('Y-m-d');

            // Filter dates to only show last 4 months
            $filteredDates = array_filter($dates, function ($date) use ($fourMonthsAgoStr) {
                return $date >= $fourMonthsAgoStr;
            });

            // Add future month projection
            $oneMonthLater = clone $lastDateObj;
            $oneMonthLater->modify('+1 month');
            $futureDates = [];
            $currentDate = clone $lastDateObj;
            $currentDate->modify('+1 day');

            while ($currentDate <= $oneMonthLater) {
                $futureDates[] = $currentDate->format('Y-m-d');
                $currentDate->modify('+1 day');
            }

            // Use filtered dates for calculations
            $dates = array_values($filteredDates);
        }

        // Calculate TRIMP values, CTL, ATL and TSB for each date
        $trimpValues = [];
        $ctlValues = [];
        $atlValues = [];
        $tsbValues = [];
        $monotonyValues = [];
        $strainValues = [];
        $formattedDates = [];

        foreach ($dates as $date) {
            $trimpValues[$date] = $this->dailyLoadData[$date]['trimp'];
        }

        // Add projected future values if needed
        if ($showFutureProjection && isset($futureDates)) {
            // Calculate average TRIMP from last 4 weeks for projection
            $lastMonthDates = array_slice($dates, -28); // Last 4 weeks
            $lastMonthTrimp = 0;
            $lastMonthDaysWithActivity = 0;

            foreach ($lastMonthDates as $date) {
                if ($this->dailyLoadData[$date]['trimp'] > 0) {
                    $lastMonthTrimp += $this->dailyLoadData[$date]['trimp'];
                    ++$lastMonthDaysWithActivity;
                }
            }

            $avgDailyTrimp = $lastMonthDaysWithActivity > 0 ? $lastMonthTrimp / $lastMonthDaysWithActivity : 0;
            $avgRestDays = $lastMonthDaysWithActivity > 0 ? (28 - $lastMonthDaysWithActivity) / 4 : 2; // Weekly rest days

            // Create projected training pattern (workout/rest days)
            foreach ($futureDates as $i => $date) {
                // Create a pattern with appropriate rest days
                $isRestDay = ($i % 7) < $avgRestDays;
                $trimpValues[$date] = $isRestDay ? 0 : $avgDailyTrimp;

                // Add this date to our array of dates to process
                $dates[] = $date;
            }

            // Resort dates to ensure chronological order
            sort($dates);
        }

        // Calculate weekly sums and standard deviations for monotony and strain
        $weeklyTrimps = [];
        $weeklyStdDevs = [];

        foreach ($dates as $index => $date) {
            // Format date for display
            $dateObj = SerializableDateTime::fromString($date);
            $formattedDates[] = $dateObj->format('M d');

            // Calculate CTL (Chronic Training Load)
            $ctlStartIndex = max(0, $index - $this->ctlDays + 1);
            $ctlWindow = array_slice($trimpValues, $ctlStartIndex, min($index + 1 - $ctlStartIndex, $this->ctlDays));
            $ctl = !empty($ctlWindow) ? array_sum($ctlWindow) / count($ctlWindow) : 0;
            $ctlValues[] = round($ctl, 1);

            // Calculate ATL (Acute Training Load)
            $atlStartIndex = max(0, $index - $this->atlDays + 1);
            $atlWindow = array_slice($trimpValues, $atlStartIndex, min($index + 1 - $atlStartIndex, $this->atlDays));
            $atl = !empty($atlWindow) ? array_sum($atlWindow) / count($atlWindow) : 0;
            $atlValues[] = round($atl, 1);

            // Calculate TSB (Training Stress Balance) = CTL - ATL
            $tsb = $ctl - $atl;
            $tsbValues[] = round($tsb, 1);

            // Calculate monotony and strain for the last 7 days
            if ($index >= 6) { // Need at least 7 days
                $weekTrimps = array_slice($trimpValues, $index - 6, 7);
                $weeklyTrimps[] = array_sum($weekTrimps);

                // Calculate standard deviation for monotony
                $mean = array_sum($weekTrimps) / 7;
                $variance = 0;

                foreach ($weekTrimps as $trimp) {
                    $variance += pow($trimp - $mean, 2);
                }

                $stdDev = sqrt($variance / 7);
                $weeklyStdDevs[] = $stdDev;

                // Monotony = daily average / standard deviation
                $monotony = ($stdDev > 0) ? $mean / $stdDev : 0;
                $monotonyValues[] = round($monotony, 2);

                // Strain = weekly TRIMP * monotony
                $strain = array_sum($weekTrimps) * $monotony;
                $strainValues[] = round($strain, 0);
            } else {
                $monotonyValues[] = null;
                $strainValues[] = null;
                $weeklyTrimps[] = null;
                $weeklyStdDevs[] = null;
            }
        }

        // For TSB, we need to determine zones (form)
        $tsbMinValue = min($tsbValues);
        $tsbMaxValue = max($tsbValues);

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'cross',
                ],
            ],
            'legend' => [
                'data' => ['Daily TRIMP', 'CTL (Fitness)', 'ATL (Fatigue)', 'TSB (Form)', 'Monotony', 'Strain'],
            ],
            'grid' => [
                'left' => '3%',
                'right' => '4%',
                'bottom' => '10%',
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'category',
                'boundaryGap' => false,
                'data' => $formattedDates,
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'name' => 'TRIMP / Load',
                    'position' => 'left',
                    'alignTicks' => true,
                    'axisLine' => [
                        'show' => true,
                        'lineStyle' => [
                            'color' => '#FC4C02',
                        ],
                    ],
                    'axisLabel' => [
                        'formatter' => '{value}',
                    ],
                ],
                [
                    'type' => 'value',
                    'name' => 'Form (TSB)',
                    'position' => 'right',
                    'alignTicks' => true,
                    'axisLine' => [
                        'show' => true,
                        'lineStyle' => [
                            'color' => '#5470C6',
                        ],
                    ],
                    'axisLabel' => [
                        'formatter' => '{value}',
                    ],
                    'min' => min(-30, $tsbMinValue),
                    'max' => max(30, $tsbMaxValue),
                ],
            ],
            'visualMap' => [
                [
                    'show' => false,
                    'type' => 'piecewise',
                    'dimension' => 0,
                    'seriesIndex' => 3,
                    'pieces' => [
                        ['gt' => -5, 'lt' => 5, 'color' => '#91CC75'],  // Green for optimal form (-5 to 5)
                        ['gt' => 5, 'lt' => 25, 'color' => '#FAC858'],  // Yellow for freshness (5 to 25)
                        ['gt' => 25, 'color' => '#EE6666'],             // Red for too fresh (>25)
                        ['gt' => -30, 'lt' => -5, 'color' => '#73C0DE'], // Blue for fatigue (-30 to -5)
                        ['lt' => -30, 'color' => '#3BA272'],            // Purple for high fatigue (<-30)
                    ],
                ],
            ],
            'series' => [
                [
                    'name' => 'Daily TRIMP',
                    'type' => 'bar',
                    'data' => array_values($trimpValues),
                    'itemStyle' => [
                        'color' => '#FC4C02',
                    ],
                    'barWidth' => '60%',
                    'yAxisIndex' => 0,
                ],
                [
                    'name' => 'CTL (Fitness)',
                    'type' => 'line',
                    'data' => $ctlValues,
                    'smooth' => true,
                    'lineStyle' => [
                        'width' => 3,
                        'color' => '#FFA500',  // Orange for CTL
                    ],
                    'yAxisIndex' => 0,
                ],
                [
                    'name' => 'ATL (Fatigue)',
                    'type' => 'line',
                    'data' => $atlValues,
                    'smooth' => true,
                    'lineStyle' => [
                        'width' => 3,
                        'color' => '#FF6347',  // Tomato for ATL
                    ],
                    'yAxisIndex' => 0,
                ],
                [
                    'name' => 'TSB (Form)',
                    'type' => 'line',
                    'data' => $tsbValues,
                    'smooth' => true,
                    'lineStyle' => [
                        'width' => 3,
                    ],
                    'yAxisIndex' => 1,
                    'markLine' => [
                        'silent' => true,
                        'lineStyle' => [
                            'color' => '#333',
                            'type' => 'dashed',
                        ],
                        'data' => [
                            [
                                'yAxis' => 5,
                                'label' => ['formatter' => 'Fresh'],
                            ],
                            [
                                'yAxis' => -5,
                                'label' => ['formatter' => 'Fatigued'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'Monotony',
                    'type' => 'line',
                    'data' => $monotonyValues,
                    'smooth' => true,
                    'symbol' => 'none',
                    'lineStyle' => [
                        'width' => 2,
                        'color' => '#9966CC',
                    ],
                    'yAxisIndex' => 0,
                ],
                [
                    'name' => 'Strain',
                    'type' => 'line',
                    'data' => $strainValues,
                    'smooth' => true,
                    'symbol' => 'none',
                    'lineStyle' => [
                        'width' => 2,
                        'color' => '#3CB371',
                        'type' => 'dashed',
                    ],
                    'yAxisIndex' => 0,
                ],
            ],
            'dataZoom' => [
                [
                    'type' => 'inside',
                    'start' => max(0, 100 - min(90, 500 / count($dates) * 100)),
                    'end' => 100,
                ],
                [
                    'type' => 'slider',
                    'start' => max(0, 100 - min(90, 500 / count($dates) * 100)),
                    'end' => 100,
                ],
            ],
        ];
    }
}
