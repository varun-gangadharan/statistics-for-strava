<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class HrvChart
{
    /**
     * @param array<string, float> $hrvData         Date strings as keys, HRV values as values
     * @param array<string, float> $readinessScores Optional readiness scores
     */
    private function __construct(
        private array $hrvData,
        private array $readinessScores = [],
    ) {
    }

    /**
     * @param array<string, float> $hrvData         Date strings as keys, HRV values as values
     * @param array<string, float> $readinessScores Optional readiness scores
     */
    public static function fromHrvData(array $hrvData, array $readinessScores = []): self
    {
        return new self(
            hrvData: $hrvData,
            readinessScores: $readinessScores,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        if (empty($this->hrvData)) {
            return [];
        }

        // Sort data by date
        $dates = array_keys($this->hrvData);
        sort($dates);

        // Format dates for display
        $formattedDates = [];
        $hrvValues = [];
        $readinessValues = [];
        $hrvBaseline = [];
        $hrvTrend = [];

        // Calculate 7-day rolling average for baseline
        $windowSize = 7;
        $rollingWindow = [];

        foreach ($dates as $index => $date) {
            // Format date for display
            $dateObj = SerializableDateTime::fromString($date);
            $formattedDates[] = $dateObj->format('M d');

            // Get HRV value
            $hrvValues[] = $this->hrvData[$date];

            // Calculate rolling average for baseline
            $rollingWindow[] = $this->hrvData[$date];
            if (count($rollingWindow) > $windowSize) {
                array_shift($rollingWindow);
            }

            if (count($rollingWindow) === $windowSize) {
                $hrvBaseline[] = array_sum($rollingWindow) / $windowSize;
            } else {
                $hrvBaseline[] = null;
            }

            // Get readiness score if available
            if (isset($this->readinessScores[$date])) {
                $readinessValues[] = $this->readinessScores[$date];
            } else {
                $readinessValues[] = null;
            }
        }

        // Calculate HRV trend (deviation from baseline)
        foreach ($hrvValues as $index => $value) {
            if ($index >= $windowSize - 1 && null !== $hrvBaseline[$index]) {
                $deviation = $value - $hrvBaseline[$index];
                $hrvTrend[] = $deviation;
            } else {
                $hrvTrend[] = null;
            }
        }

        // Calculate min/max for y-axis with some padding
        $minHrv = floor(min($hrvValues) - 5);
        $maxHrv = ceil(max($hrvValues) + 5);

        // Determine visualization thresholds for HRV baseline deviation
        $hrvUpperThreshold = 5; // Typical threshold for elevated HRV (recovery)
        $hrvLowerThreshold = -5; // Typical threshold for reduced HRV (stress/fatigue)

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'cross',
                ],
            ],
            'legend' => [
                'data' => ['HRV (ms)', '7-day Baseline', 'HRV Trend', 'Readiness'],
                'selected' => [
                    'Readiness' => !empty($this->readinessScores),
                ],
            ],
            'grid' => [
                'left' => '3%',
                'right' => '4%',
                'bottom' => '3%',
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
                    'name' => 'HRV (ms)',
                    'min' => $minHrv,
                    'max' => $maxHrv,
                    'position' => 'left',
                ],
                [
                    'type' => 'value',
                    'name' => 'Trend / Readiness',
                    'min' => -20,
                    'max' => 20,
                    'interval' => 5,
                    'position' => 'right',
                    'axisLine' => [
                        'show' => true,
                        'lineStyle' => [
                            'color' => '#5470C6',
                        ],
                    ],
                    'splitLine' => [
                        'show' => false,
                    ],
                ],
            ],
            'visualMap' => [
                [
                    'show' => false,
                    'type' => 'piecewise',
                    'dimension' => 0,
                    'seriesIndex' => 2,
                    'pieces' => [
                        ['gt' => $hrvUpperThreshold, 'color' => '#91CC75'], // Green for elevated HRV (good recovery)
                        ['gt' => $hrvLowerThreshold, 'lte' => $hrvUpperThreshold, 'color' => '#73C0DE'], // Blue for normal HRV
                        ['lte' => $hrvLowerThreshold, 'color' => '#EE6666'], // Red for reduced HRV (stress/fatigue)
                    ],
                ],
            ],
            'series' => [
                [
                    'name' => 'HRV (ms)',
                    'type' => 'line',
                    'data' => $hrvValues,
                    'symbol' => 'circle',
                    'symbolSize' => 6,
                    'itemStyle' => [
                        'color' => '#FC4C02',
                    ],
                    'lineStyle' => [
                        'width' => 3,
                    ],
                    'yAxisIndex' => 0,
                ],
                [
                    'name' => '7-day Baseline',
                    'type' => 'line',
                    'data' => $hrvBaseline,
                    'symbol' => 'none',
                    'smooth' => true,
                    'lineStyle' => [
                        'width' => 2,
                        'color' => '#999999',
                        'type' => 'dashed',
                    ],
                    'yAxisIndex' => 0,
                ],
                [
                    'name' => 'HRV Trend',
                    'type' => 'bar',
                    'data' => $hrvTrend,
                    'barWidth' => '60%',
                    'yAxisIndex' => 1,
                ],
                !empty($this->readinessScores) ? [
                    'name' => 'Readiness',
                    'type' => 'line',
                    'data' => $readinessValues,
                    'symbol' => 'diamond',
                    'symbolSize' => 8,
                    'lineStyle' => [
                        'width' => 2,
                        'color' => '#9966CC',
                    ],
                    'yAxisIndex' => 1,
                ] : [],
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
