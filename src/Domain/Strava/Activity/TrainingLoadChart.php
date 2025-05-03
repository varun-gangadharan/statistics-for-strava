<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class TrainingLoadChart
{
    private const int DEFAULT_DISPLAY_DAYS = 42;

    /**
     * @param array<string, array{trimp: float, duration: int, intensity: float}> $dailyLoadData Date ('Y-m-d') => Load Data
     */
    private function __construct(
        private array $dailyLoadData,
        private int $ctlDays = 42, // ~6 weeks for Chronic Training Load
        private int $atlDays = 7,  // 7 days for Acute Training Load
        /** @var array<string, array{ctl: float, atl: float, tsb: float, trimp: float}>|null */
        private ?array $precomputedMetrics = null,
    ) {
    }

    /**
     * @param array<string, array{trimp: float, duration: int, intensity: float}> $dailyLoadData
     */
    public static function fromDailyLoadData(
        array $dailyLoadData,
        int $ctlDays = 42,
        int $atlDays = 7,
        /** @param array<string, array{ctl: float, atl: float, tsb: float, trimp: float}>|null $precomputedMetrics */
        ?array $precomputedMetrics = null,
    ): self {
        return new self(
            dailyLoadData: $dailyLoadData,
            ctlDays: $ctlDays,
            atlDays: $atlDays,
            precomputedMetrics: $precomputedMetrics,
        );
    }

    /**
     * Calculates dynamic axis range with percentage-based buffers.
     *
     * @param float[]    $values           Data values
     * @param float      $bufferPercentage Percentage buffer (e.g., 0.1 for 10%)
     * @param float|null $forceMin         Optional minimum value for the axis
     * @param float|null $forceMax         Optional maximum value for the axis
     * @param float      $minAbsValue      Absolute minimum allowed value (e.g., 0 for load)
     * @param float      $step             Rounding step (e.g., 5 or 10)
     *
     * @return array{min: float, max: float}
     */
    private function calculateAxisRange(
        array $values,
        float $bufferPercentage,
        ?float $forceMin = null,
        ?float $forceMax = null,
        float $minAbsValue = -INF, // Allow negative for TSB
        float $step = 10.0,
    ): array {
        if (empty($values)) {
            return ['min' => $forceMin ?? $minAbsValue, 'max' => $forceMax ?? $minAbsValue + $step * 5]; // Default range if no data
        }

        $dataMin = min($values);
        $dataMax = max($values);

        // Handle case where all values are the same
        if ($dataMin == $dataMax) {
            $spread = abs($dataMax * $bufferPercentage * 2); // Create a small spread based on the value
            if ($spread < $step / 2) { // Ensure minimum spread if value is small or zero
                $spread = $step;
            }
            $minCalc = $dataMin - $spread / 2;
            $maxCalc = $dataMax + $spread / 2;
        } else {
            $spread = $dataMax - $dataMin;
            $buffer = $spread * $bufferPercentage;
            $minCalc = $dataMin - $buffer;
            $maxCalc = $dataMax + $buffer;
        }

        // Apply absolute minimum value constraint (e.g., axes shouldn't go below 0 for load)
        $minCalc = max($minAbsValue, $minCalc);

        // Apply forced min/max if provided
        $finalMin = null !== $forceMin ? min($forceMin, $minCalc) : $minCalc;
        $finalMax = null !== $forceMax ? max($forceMax, $maxCalc) : $maxCalc;

        // Round min down and max up to the nearest step
        $finalMin = floor($finalMin / $step) * $step;
        $finalMax = ceil($finalMax / $step) * $step;

        // Ensure min is strictly less than max after rounding
        if ($finalMin >= $finalMax) {
            $finalMax = $finalMin + $step;
        }

        return ['min' => $finalMin, 'max' => $finalMax];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        if (empty($this->dailyLoadData)) {
            return [];
        }

        // Ensure data is sorted chronologically for calculations.
        $sortedDailyLoadData = $this->dailyLoadData;
        ksort($sortedDailyLoadData);

        $allDates = array_keys($sortedDailyLoadData);

        $lastDateStr = end($allDates);
        $lastDateObj = SerializableDateTime::fromString($lastDateStr);

        // --- Date Filtering: Default to last 6 weeks (42 days) ---
        $filterStartDate = $lastDateObj->modify('-'.(self::DEFAULT_DISPLAY_DAYS - 1).' days'); // -41 to get 42 days inclusive
        $filterStartDateStr = $filterStartDate->format('Y-m-d');

        // Filter dates to only include the specified period relative to the last data point
        $displayDates = array_filter($allDates, function ($date) use ($filterStartDateStr, $lastDateStr) {
            return $date >= $filterStartDateStr && $date <= $lastDateStr;
        });
        sort($displayDates); // Ensure filtered dates are sorted

        if (empty($displayDates)) {
            return [];
        }

        // --- Set the dates to be processed ---
        $dates = $displayDates; // Use the filtered dates for display

        // --- Calculate TRIMP, CTL, ATL, TSB ---
        $trimpValues = []; // Daily TRIMP for the chart period
        $ctlValues = [];
        $atlValues = [];
        $tsbValues = [];
        $formattedDates = []; // For X-axis labels

        // We need a buffer of past data (up to ctlDays) to calculate initial CTL/ATL correctly
        // Get data from display_start_date - ctlDays up to the last display date
        $calculationStartDate = SerializableDateTime::fromString(reset($dates))->modify('-'.$this->ctlDays.' days');
        $calculationStartDateStr = $calculationStartDate->format('Y-m-d');

        $calculationData = []; // Only historical TRIMP values needed for calculation
        foreach ($sortedDailyLoadData as $date => $load) {
            // Include data from the buffer start date up to the last date in our display window
            if ($date >= $calculationStartDateStr && $date <= end($dates)) {
                $calculationData[$date] = $load['trimp'] ?? 0;
            }
        }
        // Ensure calculation data is sorted (although ksort on input helps)
        ksort($calculationData);

        // Now iterate through the final dates array (last N days)
        // and collect values either from provided precomputed metrics or via local EWMA calculation.
        $prevCtl = 0;
        $prevAtl = 0;
        $decayCtl = exp(-1 / $this->ctlDays);
        $decayAtl = exp(-1 / $this->atlDays);
        $hasPrecomputed = null !== $this->precomputedMetrics;

        foreach ($dates as $currentDate) {
            if ($hasPrecomputed && isset($this->precomputedMetrics[$currentDate])) {
                $row = $this->precomputedMetrics[$currentDate];
                $todayTrimp = $row['trimp'];
                $ctl = $row['ctl'];
                $atl = $row['atl'];
                $tsb = $row['tsb'];
            } else {
                // Use TRIMP from calculationData (includes buffer) or 0 if missing
                $todayTrimp = $calculationData[$currentDate] ?? 0;
                // Exponentially Weighted Moving Average (EWMA)
                $ctl = ($prevCtl * $decayCtl) + ($todayTrimp * (1 - $decayCtl));
                $atl = ($prevAtl * $decayAtl) + ($todayTrimp * (1 - $decayAtl));
                $tsb = $ctl - $atl;
            }

            // Store values for the chart *only for dates within the display window*
            $trimpValues[] = round($todayTrimp, 1);
            $ctlValues[] = round($ctl, 1);
            $atlValues[] = round($atl, 1);
            $tsbValues[] = round($tsb, 1);
            $formattedDates[] = SerializableDateTime::fromString($currentDate)->format('M d');

            // Update previous values for next iteration if using local calculation
            if (!$hasPrecomputed) {
                $prevCtl = $ctl;
                $prevAtl = $atl;
            }
        }

        // NEW: Calculate dynamic Y-axis ranges with buffers
        $bufferPercent = 0.1; // 10% buffer

        // Calculate dynamic ranges only based on the values within the display window
        $tsbAxisRange = $this->calculateAxisRange($tsbValues, $bufferPercent, -30.0, 30.0, -INF, 5.0); // Force TSB range to include -30 to +30, round to 5
        $loadAxisRange = $this->calculateAxisRange(array_merge($ctlValues, $atlValues), $bufferPercent, null, null, 0.0, 10.0); // Min 0 for Load, round to 10
        $trimpAxisRange = $this->calculateAxisRange($trimpValues, $bufferPercent * 2, null, null, 0.0, 20.0); // Min 0 for TRIMP, bigger buffer, round to 20

        // Define number of data points for zoom
        $numDataPoints = count($dates);
        // Default zoom start index (show last 42 days)
        $defaultZoomStartIndex = max(0, $numDataPoints - self::DEFAULT_DISPLAY_DAYS);
        $defaultZoomEndIndex = max(0, $numDataPoints - 1);

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'cross',
                    'link' => [['xAxisIndex' => 'all']], // Link both x-axes for tooltip/crosshair
                    'label' => ['backgroundColor' => '#6a7985'],
                ],
            ],
            'legend' => [
                'data' => ['CTL (Fitness)', 'ATL (Fatigue)', 'TSB (Form)', 'Daily TRIMP'], // Order might change slightly depending on visual preference
                'top' => '5%', // Position legend at the top
            ],
            'axisPointer' => [ // Ensure crosshair spans both grids
                'link' => ['xAxisIndex' => 'all'],
            ],
            // Reorganized grid layout
            'grid' => [
                // Grid 0: Top grid for CTL, ATL, TSB (takes more height)
                [
                    'left' => '5%',
                    'right' => '8%',
                    'top' => '15%',
                    'height' => '55%',
                    'containLabel' => false,
                ],
                // Grid 1: Bottom grid for Daily TRIMP
                [
                    'left' => '5%',
                    'right' => '8%',
                    'top' => '75%',
                    'height' => '15%',
                    'containLabel' => false,
                ],
            ],
            // Reorganized X-Axes to match grids
            'xAxis' => [
                // x-Axis 0: Linked to grid 0 (Top: CTL, ATL, TSB)
                [
                    'type' => 'category',
                    'gridIndex' => 0, // Assign to top grid
                    'data' => $formattedDates,
                    'boundaryGap' => true, // Keep gap for line charts
                    'axisLine' => ['onZero' => false],
                    'axisLabel' => ['show' => false], // Hide labels to avoid overlap
                    'axisTick' => ['show' => false], // Hide ticks
                ],
                // x-Axis 1: Linked to grid 1 (Bottom: Daily TRIMP)
                [
                    'type' => 'category',
                    'gridIndex' => 1, // Assign to bottom grid
                    'data' => $formattedDates,
                    'boundaryGap' => true, // Keep gap for bar charts
                    'axisLine' => ['onZero' => true],
                    'position' => 'bottom',
                    'axisLabel' => ['show' => true], // Show labels on the bottom-most axis
                    'axisTick' => ['show' => true], // Show ticks
                ],
            ],
            // Reorganized Y-Axes to match grids and dynamic ranges
            'yAxis' => [
                // y-Axis 0: Linked to grid 1 (Bottom: Daily TRIMP) - Position Left
                [
                    'type' => 'value',
                    'name' => 'Daily TRIMP',
                    'nameLocation' => 'middle',
                    'nameGap' => 35,
                    'gridIndex' => 1, // Assign to bottom grid
                    'position' => 'left',
                    'axisLabel' => ['formatter' => '{value}'],
                    'min' => $trimpAxisRange['min'], // Dynamic min
                    'max' => $trimpAxisRange['max'], // Dynamic max
                    'splitLine' => ['show' => true], // Show horizontal grid lines
                    'axisLine' => ['show' => true, 'lineStyle' => ['color' => '#cccccc']],
                ],
                // y-Axis 1: Linked to grid 0 (Top: CTL, ATL) - Position Left
                [
                    'type' => 'value',
                    'name' => 'Load (CTL/ATL)',
                    'nameLocation' => 'middle',
                    'nameGap' => 35,
                    'gridIndex' => 0, // Assign to top grid
                    'position' => 'left',
                    'alignTicks' => true, // Align ticks if possible (might not perfectly align with TSB)
                    'axisLine' => ['show' => true, 'lineStyle' => ['color' => '#cccccc']],
                    'axisLabel' => ['formatter' => '{value}'],
                    'min' => $loadAxisRange['min'], // Dynamic min
                    'max' => $loadAxisRange['max'], // Dynamic max
                    'splitLine' => ['show' => true], // Show horizontal grid lines
                ],
                // y-Axis 2: Linked to grid 0 (Top: TSB) - Position Right
                [
                    'type' => 'value',
                    'name' => 'Form (TSB)',
                    'nameLocation' => 'middle',
                    'nameGap' => 45, // Increased gap for right axis
                    'gridIndex' => 0, // Assign to top grid
                    'position' => 'right',
                    'alignTicks' => true, // Align ticks
                    'axisLine' => ['show' => true, 'lineStyle' => ['color' => '#5470C6']], // TSB Color
                    'axisLabel' => ['formatter' => '{value}'],
                    'min' => $tsbAxisRange['min'], // Dynamic min based on data spread + buffer
                    'max' => $tsbAxisRange['max'], // Dynamic max based on data spread + buffer
                    'splitLine' => ['show' => false], // Hide TSB grid lines to avoid clutter
                ],
            ],
            // Modified series order and axis associations
            'series' => [
                // Series associated with Top Grid (Grid 0)
                [
                    'name' => 'CTL (Fitness)', 'type' => 'line', 'data' => $ctlValues, 'smooth' => true,
                    'symbol' => 'none', 'lineStyle' => ['width' => 3, 'color' => '#3CB371'],
                    'xAxisIndex' => 0, // Use x-Axis 0 (linked to grid 0)
                    'yAxisIndex' => 1, // Use y-Axis 1 (Load axis, linked to grid 0)
                ],
                [
                    'name' => 'ATL (Fatigue)', 'type' => 'line', 'data' => $atlValues, 'smooth' => true,
                    'symbol' => 'none', 'lineStyle' => ['width' => 3, 'color' => '#FF6347'],
                    'xAxisIndex' => 0, // Use x-Axis 0 (linked to grid 0)
                    'yAxisIndex' => 1, // Use y-Axis 1 (Load axis, linked to grid 0)
                ],
                [
                    'name' => 'TSB (Form)', 'type' => 'line', 'data' => $tsbValues, 'smooth' => true,
                    'symbol' => 'none', 'lineStyle' => ['width' => 2, 'color' => '#5470C6'],
                    'xAxisIndex' => 0, // Use x-Axis 0 (linked to grid 0)
                    'yAxisIndex' => 2, // Use y-Axis 2 (TSB axis, linked to grid 0)
                    'markLine' => [
                        'silent' => true, 'lineStyle' => ['color' => '#333', 'type' => 'dashed'],
                        'data' => [
                            ['yAxis' => 5, 'label' => ['formatter' => 'Fresh', 'position' => 'insideEndTop']],
                            // ['yAxis' => -5, 'label' => ['formatter' => 'Neutral', 'position' => 'insideEndTop']], // Reduced zones
                            ['yAxis' => -15, 'label' => ['formatter' => 'Optimal', 'position' => 'insideEndTop']], // Common zones
                            ['yAxis' => -30, 'label' => ['formatter' => 'Fatigued', 'position' => 'insideEndTop']], // Common zones
                        ],
                        'label' => ['distance' => [0, -5]], // Adjust label position relative to line
                    ],
                ],
                // Series associated with Bottom Grid (Grid 1)
                [
                    'name' => 'Daily TRIMP', 'type' => 'bar', 'data' => $trimpValues,
                    'itemStyle' => ['color' => '#FC4C02'], 'barWidth' => '60%',
                    'xAxisIndex' => 1, // Use x-Axis 1 (linked to grid 1)
                    'yAxisIndex' => 0, // Use y-Axis 0 (TRIMP axis, linked to grid 1)
                    'emphasis' => ['itemStyle' => ['opacity' => 0.8]],
                ],
            ],
            // dataZoom adjusted to show last 6 weeks by default
            'dataZoom' => [
                [
                    'type' => 'inside',
                    'xAxisIndex' => [0, 1], // Apply zoom to both x-axes
                    'startValue' => $defaultZoomStartIndex, // Start index for last 42 days
                    'endValue' => $defaultZoomEndIndex,   // End index (last data point)
                    'minValueSpan' => 14, // Allow zooming in to 2 weeks minimum
                    'maxValueSpan' => $numDataPoints, // Allow zooming out to full range shown
                ],
                [
                    'type' => 'slider',
                    'xAxisIndex' => [0, 1], // Apply slider to both x-axes
                    'startValue' => $defaultZoomStartIndex, // Start index for last 42 days
                    'endValue' => $defaultZoomEndIndex,   // End index (last data point)
                    'bottom' => '2%',
                    'height' => '3%',
                    'minValueSpan' => 14, // Match inside zoom min span
                    'maxValueSpan' => $numDataPoints, // Match inside zoom max span
                ],
            ],
        ];
    }
}
