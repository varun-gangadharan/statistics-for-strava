<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class Vo2MaxTrendsChart
{
    /**
     * @param array<string, float> $vo2MaxValues Date strings as keys, VO2 Max values as values
     */
    private function __construct(
        private array $vo2MaxValues,
    ) {
    }

    /**
     * @param array<string, float> $vo2MaxValues Date strings as keys, VO2 Max values as values
     */
    public static function fromVo2MaxData(array $vo2MaxValues): self
    {
        return new self(
            vo2MaxValues: $vo2MaxValues,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        if (empty($this->vo2MaxValues)) {
            return [];
        }

        $dates = array_keys($this->vo2MaxValues);
        $vo2MaxValues = array_values($this->vo2MaxValues);
        
        // Calculate min/max for y-axis with some padding
        $minVo2Max = floor(min($vo2MaxValues) - 2);
        $maxVo2Max = ceil(max($vo2MaxValues) + 2);
        
        // Format dates for display
        $formattedDates = array_map(function (string $dateString) {
            $date = SerializableDateTime::fromString($dateString);
            return $date->format('M d');
        }, $dates);

        // Calculate moving average if we have enough data points
        $movingAverageData = [];
        if (count($vo2MaxValues) >= 7) {
            $window = 7; // 7-day moving average
            for ($i = 0; $i < count($vo2MaxValues); $i++) {
                if ($i < $window - 1) {
                    $movingAverageData[] = null; // Not enough data for full window
                } else {
                    $sum = 0;
                    for ($j = 0; $j < $window; $j++) {
                        $sum += $vo2MaxValues[$i - $j];
                    }
                    $movingAverageData[] = $sum / $window;
                }
            }
        }

        return [
            'grid' => [
                'left' => '3%',
                'right' => '4%',
                'bottom' => '3%',
                'containLabel' => true,
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'cross',
                    'label' => [
                        'backgroundColor' => '#6a7985'
                    ]
                ]
            ],
            'legend' => [
                'data' => ['VO2 Max', count($movingAverageData) > 0 ? '7-day Average' : '']
            ],
            'xAxis' => [
                'type' => 'category',
                'boundaryGap' => false,
                'data' => $formattedDates,
            ],
            'yAxis' => [
                'type' => 'value',
                'min' => $minVo2Max,
                'max' => $maxVo2Max,
                'name' => 'VO2 Max (ml/kg/min)',
                'nameLocation' => 'middle',
                'nameGap' => 40,
            ],
            'series' => [
                [
                    'name' => 'VO2 Max',
                    'type' => 'line',
                    'data' => $vo2MaxValues,
                    'itemStyle' => [
                        'color' => '#FC4C02' // Strava orange
                    ],
                    'areaStyle' => [
                        'color' => [
                            'type' => 'linear',
                            'x' => 0,
                            'y' => 0,
                            'x2' => 0,
                            'y2' => 1,
                            'colorStops' => [
                                [
                                    'offset' => 0,
                                    'color' => 'rgba(252, 76, 2, 0.4)' // Strava orange with opacity
                                ],
                                [
                                    'offset' => 1,
                                    'color' => 'rgba(252, 76, 2, 0.1)'
                                ]
                            ]
                        ]
                    ],
                ],
                count($movingAverageData) > 0 ? [
                    'name' => '7-day Average',
                    'type' => 'line',
                    'data' => $movingAverageData,
                    'smooth' => true,
                    'lineStyle' => [
                        'width' => 2,
                        'color' => '#2684FF' // Blue color for moving average
                    ],
                    'symbol' => 'none',
                ] : [],
            ],
            'dataZoom' => [
                [
                    'type' => 'inside',
                    'start' => 0,
                    'end' => 100
                ]
            ]
        ];
    }
}
