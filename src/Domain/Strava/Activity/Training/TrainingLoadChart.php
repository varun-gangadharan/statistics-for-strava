<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity\Training;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class TrainingLoadChart
{
    private const int DEFAULT_DISPLAY_DAYS = 42;

    /**
     * @param array<string, array{trimp: float, duration: int, intensity: float}> $dailyLoadData Date ('Y-m-d') => Load Data
     */
    private function __construct(
        private array $dailyLoadData,
        private int $ctlDays = 42,
        private int $atlDays = 7,
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
        /* @param array<string, array{ctl: float, atl: float, tsb: float, trimp: float}>|null $precomputedMetrics */
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
     * @return array{min: float, max: float}
     */
    private function calculateAxisRange(
        array $values,
        float $bufferPercentage,
        ?float $forceMin = null,
        ?float $forceMax = null,
        float $minAbsValue = -INF,
        float $step = 10.0,
    ): array {
        if (empty($values)) {
            return ['min' => $forceMin ?? $minAbsValue, 'max' => $forceMax ?? $minAbsValue + $step * 5];
        }

        $dataMin = min($values);
        $dataMax = max($values);

        if ($dataMin == $dataMax) {
            $spread = abs($dataMax * $bufferPercentage * 2);
            if ($spread < $step / 2) {
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

        $minCalc = max($minAbsValue, $minCalc);

        $finalMin = null !== $forceMin ? min($forceMin, $minCalc) : $minCalc;
        $finalMax = null !== $forceMax ? max($forceMax, $maxCalc) : $maxCalc;

        $finalMin = floor($finalMin / $step) * $step;
        $finalMax = ceil($finalMax / $step) * $step;

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

        $sortedDailyLoadData = $this->dailyLoadData;
        ksort($sortedDailyLoadData);

        $allDates = array_keys($sortedDailyLoadData);

        $lastDateStr = end($allDates);
        $lastDateObj = SerializableDateTime::fromString($lastDateStr);

        $filterStartDate = $lastDateObj->modify('-'.(self::DEFAULT_DISPLAY_DAYS - 1).' days');
        $filterStartDateStr = $filterStartDate->format('Y-m-d');

        $displayDates = array_filter($allDates, function ($date) use ($filterStartDateStr, $lastDateStr) {
            return $date >= $filterStartDateStr && $date <= $lastDateStr;
        });
        sort($displayDates);

        if (empty($displayDates)) {
            return [];
        }

        $dates = $displayDates;

        $trimpValues = [];
        $ctlValues = [];
        $atlValues = [];
        $tsbValues = [];
        $formattedDates = [];

        $calculationStartDate = SerializableDateTime::fromString(reset($dates))->modify('-'.$this->ctlDays.' days');
        $calculationStartDateStr = $calculationStartDate->format('Y-m-d');

        $calculationData = [];
        foreach ($sortedDailyLoadData as $date => $load) {
            if ($date >= $calculationStartDateStr && $date <= end($dates)) {
                $calculationData[$date] = $load['trimp'] ?? 0;
            }
        }
        ksort($calculationData);

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
                $todayTrimp = $calculationData[$currentDate] ?? 0;
                $ctl = ($prevCtl * $decayCtl) + ($todayTrimp * (1 - $decayCtl));
                $atl = ($prevAtl * $decayAtl) + ($todayTrimp * (1 - $decayAtl));
                $tsb = $ctl - $atl;
            }

            $trimpValues[] = round($todayTrimp, 1);
            $ctlValues[] = round($ctl, 1);
            $atlValues[] = round($atl, 1);
            $tsbValues[] = round($tsb, 1);
            $formattedDates[] = SerializableDateTime::fromString($currentDate)->format('M d');

            if (!$hasPrecomputed) {
                $prevCtl = $ctl;
                $prevAtl = $atl;
            }
        }

        $bufferPercent = 0.1;

        $tsbAxisRange = $this->calculateAxisRange($tsbValues, $bufferPercent, -30.0, 30.0, -INF, 5.0);
        $loadAxisRange = $this->calculateAxisRange(array_merge($ctlValues, $atlValues), $bufferPercent, null, null, 0.0);
        $trimpAxisRange = $this->calculateAxisRange($trimpValues, $bufferPercent * 2, null, null, 0.0, 20.0);

        $numDataPoints = count($dates);
        $defaultZoomStartIndex = max(0, $numDataPoints - self::DEFAULT_DISPLAY_DAYS);
        $defaultZoomEndIndex = max(0, $numDataPoints - 1);

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'cross',
                    'link' => [['xAxisIndex' => 'all']],
                    'label' => ['backgroundColor' => '#6a7985'],
                ],
            ],
            'legend' => [
                'data' => ['CTL (Fitness)', 'ATL (Fatigue)', 'TSB (Form)', 'Daily TRIMP'],
                'top' => '5%',
            ],
            'axisPointer' => [
                'link' => ['xAxisIndex' => 'all'],
            ],
            'grid' => [
                [
                    'left' => '5%',
                    'right' => '8%',
                    'top' => '15%',
                    'height' => '55%',
                    'containLabel' => false,
                ],
                [
                    'left' => '5%',
                    'right' => '8%',
                    'top' => '75%',
                    'height' => '15%',
                    'containLabel' => false,
                ],
            ],
            'xAxis' => [
                [
                    'type' => 'category',
                    'gridIndex' => 0,
                    'data' => $formattedDates,
                    'boundaryGap' => true,
                    'axisLine' => ['onZero' => false],
                    'axisLabel' => ['show' => false],
                    'axisTick' => ['show' => false],
                ],
                [
                    'type' => 'category',
                    'gridIndex' => 1,
                    'data' => $formattedDates,
                    'boundaryGap' => true,
                    'axisLine' => ['onZero' => true],
                    'position' => 'bottom',
                    'axisLabel' => ['show' => true],
                    'axisTick' => ['show' => true],
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'name' => 'Daily TRIMP',
                    'nameLocation' => 'middle',
                    'nameGap' => 35,
                    'gridIndex' => 1,
                    'position' => 'left',
                    'axisLabel' => ['formatter' => '{value}'],
                    'min' => $trimpAxisRange['min'],
                    'max' => $trimpAxisRange['max'],
                    'splitLine' => ['show' => true],
                    'axisLine' => ['show' => true, 'lineStyle' => ['color' => '#cccccc']],
                ],
                [
                    'type' => 'value',
                    'name' => 'Load (CTL/ATL)',
                    'nameLocation' => 'middle',
                    'nameGap' => 35,
                    'gridIndex' => 0,
                    'position' => 'left',
                    'alignTicks' => true,
                    'axisLine' => ['show' => true, 'lineStyle' => ['color' => '#cccccc']],
                    'axisLabel' => ['formatter' => '{value}'],
                    'min' => $loadAxisRange['min'],
                    'max' => $loadAxisRange['max'],
                    'splitLine' => ['show' => true],
                ],
                [
                    'type' => 'value',
                    'name' => 'Form (TSB)',
                    'nameLocation' => 'middle',
                    'nameGap' => 45,
                    'gridIndex' => 0,
                    'position' => 'right',
                    'alignTicks' => true,
                    'axisLine' => ['show' => true, 'lineStyle' => ['color' => '#5470C6']],
                    'axisLabel' => ['formatter' => '{value}'],
                    'min' => $tsbAxisRange['min'],
                    'max' => $tsbAxisRange['max'],
                    'splitLine' => ['show' => false],
                ],
            ],
            'series' => [
                [
                    'name' => 'CTL (Fitness)', 'type' => 'line', 'data' => $ctlValues, 'smooth' => true,
                    'symbol' => 'none', 'lineStyle' => ['width' => 3, 'color' => '#3CB371'],
                    'xAxisIndex' => 0,
                    'yAxisIndex' => 1,
                ],
                [
                    'name' => 'ATL (Fatigue)', 'type' => 'line', 'data' => $atlValues, 'smooth' => true,
                    'symbol' => 'none', 'lineStyle' => ['width' => 3, 'color' => '#FF6347'],
                    'xAxisIndex' => 0,
                    'yAxisIndex' => 1,
                ],
                [
                    'name' => 'TSB (Form)', 'type' => 'line', 'data' => $tsbValues, 'smooth' => true,
                    'symbol' => 'none', 'lineStyle' => ['width' => 2, 'color' => '#5470C6'],
                    'xAxisIndex' => 0,
                    'yAxisIndex' => 2,
                    'markLine' => [
                        'silent' => true, 'lineStyle' => ['color' => '#333', 'type' => 'dashed'],
                        'data' => [
                            ['yAxis' => 5, 'label' => ['formatter' => 'Fresh']],
                            ['yAxis' => -5, 'label' => ['formatter' => 'Optimal']],
                            ['yAxis' => -15, 'label' => ['formatter' => 'Fatigued']],
                        ],
                        'label' => ['distance' => [0, -5]],
                    ],
                ],
                [
                    'name' => 'Daily TRIMP', 'type' => 'bar', 'data' => $trimpValues,
                    'itemStyle' => ['color' => '#FC4C02'], 'barWidth' => '60%',
                    'xAxisIndex' => 1,
                    'yAxisIndex' => 0,
                    'emphasis' => ['itemStyle' => ['opacity' => 0.8]],
                ],
            ],
            'dataZoom' => [
                [
                    'type' => 'inside',
                    'xAxisIndex' => [0, 1],
                    'startValue' => $defaultZoomStartIndex,
                    'endValue' => $defaultZoomEndIndex,
                    'minValueSpan' => 14,
                    'maxValueSpan' => $numDataPoints,
                ],
                [
                    'type' => 'slider',
                    'xAxisIndex' => [0, 1],
                    'startValue' => $defaultZoomStartIndex,
                    'endValue' => $defaultZoomEndIndex,
                    'bottom' => '2%',
                    'height' => '3%',
                    'minValueSpan' => 14,
                    'maxValueSpan' => $numDataPoints,
                ],
            ],
        ];
    }
}
