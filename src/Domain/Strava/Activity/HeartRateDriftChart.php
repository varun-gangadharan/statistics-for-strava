<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

final readonly class HeartRateDriftChart
{
    /**
     * @param array<int, int>   $time      Time in seconds from start
     * @param array<int, int>   $heartRate Heart rate values
     * @param array<int, float> $speed     Speed values (optional)
     * @param array<int, float> $power     Power values (optional)
     */
    private function __construct(
        private array $time,
        private array $heartRate,
        private array $speed = [],
        private array $power = [],
    ) {
    }

    /**
     * @param array<int, int>   $time      Time in seconds from start
     * @param array<int, int>   $heartRate Heart rate values
     * @param array<int, float> $speed     Speed values (optional)
     * @param array<int, float> $power     Power values (optional)
     */
    public static function fromActivityData(
        array $time,
        array $heartRate,
        array $speed = [],
        array $power = [],
    ): self {
        return new self(
            time: $time,
            heartRate: $heartRate,
            speed: $speed,
            power: $power,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        if (empty($this->time) || empty($this->heartRate) || count($this->time) !== count($this->heartRate)) {
            return [];
        }

        // Calculate time in minutes for x-axis
        $timeInMinutes = [];
        foreach ($this->time as $seconds) {
            $timeInMinutes[] = round($seconds / 60, 1);
        }

        // Calculate drift metrics
        $hasPower = !empty($this->power) && count($this->power) === count($this->heartRate);
        $hasSpeed = !empty($this->speed) && count($this->speed) === count($this->heartRate);

        // Calculate decoupling or cardiac drift
        $firstHalf = [];
        $secondHalf = [];
        $midpointIndex = (int) floor(count($this->heartRate) / 2);

        $hrFirstHalf = array_slice($this->heartRate, 0, $midpointIndex);
        $hrSecondHalf = array_slice($this->heartRate, $midpointIndex);

        $averageHrFirstHalf = array_sum($hrFirstHalf) / count($hrFirstHalf);
        $averageHrSecondHalf = array_sum($hrSecondHalf) / count($hrSecondHalf);

        $driftPercentage = (($averageHrSecondHalf - $averageHrFirstHalf) / $averageHrFirstHalf) * 100;
        $driftPercentage = round($driftPercentage, 1);

        // Calculate power/HR or pace/HR ratio for decoupling if available
        $decouplingPercentage = null;
        $ratioData = [];

        if ($hasPower) {
            // Calculate power:HR ratio
            $ratioData = [];
            foreach (range(0, count($this->heartRate) - 1) as $i) {
                if ($this->heartRate[$i] > 0) {
                    $ratioData[] = $this->power[$i] / $this->heartRate[$i];
                } else {
                    $ratioData[] = 0;
                }
            }

            $ratioFirstHalf = array_slice($ratioData, 0, $midpointIndex);
            $ratioSecondHalf = array_slice($ratioData, $midpointIndex);

            $averageRatioFirstHalf = array_sum($ratioFirstHalf) / count($ratioFirstHalf);
            $averageRatioSecondHalf = array_sum($ratioSecondHalf) / count($ratioSecondHalf);

            if($averageRatioFirstHalf === 0){
                return [];
            }

            $decouplingPercentage = (($averageRatioFirstHalf - $averageRatioSecondHalf) / $averageRatioFirstHalf) * 100;
            $decouplingPercentage = round($decouplingPercentage, 1);
        } elseif ($hasSpeed) {
            // Calculate pace:HR ratio (using speed)
            $ratioData = [];
            foreach (range(0, count($this->heartRate) - 1) as $i) {
                if ($this->heartRate[$i] > 0) {
                    $ratioData[] = $this->speed[$i] / $this->heartRate[$i];
                } else {
                    $ratioData[] = 0;
                }
            }

            $ratioFirstHalf = array_slice($ratioData, 0, $midpointIndex);
            $ratioSecondHalf = array_slice($ratioData, $midpointIndex);

            $averageRatioFirstHalf = array_sum($ratioFirstHalf) / count($ratioFirstHalf);
            $averageRatioSecondHalf = array_sum($ratioSecondHalf) / count($ratioSecondHalf);

            $decouplingPercentage = (($averageRatioFirstHalf - $averageRatioSecondHalf) / $averageRatioFirstHalf) * 100;
            $decouplingPercentage = round($decouplingPercentage, 1);
        }

        // Smooth the data for visualization (moving average) - reduced for short runs
        $windowSize = count($this->heartRate) < 500 ? 3 : 10; // Smaller window for short runs (3-5km)
        $smoothedHeartRate = $this->movingAverage($this->heartRate, $windowSize);
        $smoothedRatio = !empty($ratioData) ? $this->movingAverage($ratioData, $windowSize) : [];

        // Calculate min/max values for y-axis with some padding
        $minHr = floor(min($this->heartRate) * 0.95);
        $maxHr = ceil(max($this->heartRate) * 1.05);

        $minRatio = !empty($ratioData) ? floor(min($ratioData) * 0.95) : 0;
        $maxRatio = !empty($ratioData) ? ceil(max($ratioData) * 1.05) : 0;

        // Determine drift quality
        $driftQuality = 'Excellent';
        if (abs($driftPercentage) > 10) {
            $driftQuality = 'Poor';
        } elseif (abs($driftPercentage) > 5) {
            $driftQuality = 'Fair';
        } elseif (abs($driftPercentage) > 3) {
            $driftQuality = 'Good';
        }

        // Determine decoupling quality if available
        $decouplingQuality = null;
        if (null !== $decouplingPercentage) {
            $decouplingQuality = 'Excellent';
            if (abs($decouplingPercentage) > 10) {
                $decouplingQuality = 'Poor';
            } elseif (abs($decouplingPercentage) > 5) {
                $decouplingQuality = 'Fair';
            } elseif (abs($decouplingPercentage) > 3) {
                $decouplingQuality = 'Good';
            }
        }

        // Build the chart config
        $series = [
            [
                'name' => 'Heart Rate',
                'type' => 'line',
                'data' => $smoothedHeartRate,
                'smooth' => true,
                'lineStyle' => [
                    'width' => 3,
                    'color' => '#FC4C02', // Strava orange
                ],
                'areaStyle' => [
                    'color' => [
                        'type' => 'linear',
                        'x' => 0, 'y' => 0, 'x2' => 0, 'y2' => 1,
                        'colorStops' => [
                            ['offset' => 0, 'color' => 'rgba(252, 76, 2, 0.4)'],
                            ['offset' => 1, 'color' => 'rgba(252, 76, 2, 0.1)'],
                        ],
                    ],
                ],
                'markArea' => [
                    'itemStyle' => [
                        'color' => 'rgba(100, 100, 100, 0.1)',
                        'borderColor' => 'rgba(100, 100, 100, 0.3)',
                        'borderWidth' => 1,
                    ],
                    'data' => [
                        [
                            ['name' => 'First Half', 'xAxis' => 'min'],
                            ['xAxis' => $timeInMinutes[$midpointIndex]],
                        ],
                        [
                            ['name' => 'Second Half', 'xAxis' => $timeInMinutes[$midpointIndex]],
                            ['xAxis' => 'max'],
                        ],
                    ],
                ],
                'markLine' => [
                    'silent' => true,
                    'lineStyle' => [
                        'color' => '#666',
                        'type' => 'dashed',
                    ],
                    'data' => [
                        ['name' => 'First Half Avg', 'yAxis' => $averageHrFirstHalf],
                        ['name' => 'Second Half Avg', 'yAxis' => $averageHrSecondHalf],
                    ],
                    'label' => [
                        'formatter' => '{b}: {c}',
                    ],
                ],
            ],
        ];

        if (!empty($smoothedRatio)) {
            $series[] = [
                'name' => $hasPower ? 'Power:HR Ratio' : 'Speed:HR Ratio',
                'type' => 'line',
                'data' => $smoothedRatio,
                'smooth' => true,
                'yAxisIndex' => 1,
                'lineStyle' => [
                    'width' => 2,
                    'color' => '#5470C6', // Blue
                ],
                'markLine' => [
                    'silent' => true,
                    'lineStyle' => [
                        'color' => '#5470C6',
                        'type' => 'dashed',
                    ],
                    'data' => [
                        ['name' => 'First Half Ratio', 'yAxis' => $averageRatioFirstHalf],
                        ['name' => 'Second Half Ratio', 'yAxis' => $averageRatioSecondHalf],
                    ],
                    'label' => [
                        'formatter' => '{b}: {c}',
                    ],
                ],
            ];
        }

        return [
            'title' => [
                'text' => 'Heart Rate Drift Analysis',
                'subtext' => "HR Drift: {$driftPercentage}% ({$driftQuality})".
                    (null !== $decouplingPercentage ? " | Decoupling: {$decouplingPercentage}% ({$decouplingQuality})" : ''),
                'left' => 'center',
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'cross',
                ],
            ],
            'legend' => [
                'data' => array_map(fn ($item) => $item['name'], $series),
                'top' => 30,
            ],
            'grid' => [
                'left' => '3%',
                'right' => '4%',
                'bottom' => '3%',
                'top' => 80,
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'category',
                'boundaryGap' => false,
                'data' => $timeInMinutes,
                'name' => 'Time (minutes)',
                'nameLocation' => 'middle',
                'nameGap' => 30,
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'name' => 'Heart Rate (BPM)',
                    'min' => $minHr,
                    'max' => $maxHr,
                    'position' => 'left',
                ],
                !empty($ratioData) ? [
                    'type' => 'value',
                    'name' => $hasPower ? 'Power:HR Ratio' : 'Speed:HR Ratio',
                    'min' => $minRatio,
                    'max' => $maxRatio,
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
                ] : [],
            ],
            'series' => $series,
            'dataZoom' => [
                [
                    'type' => 'inside',
                    'start' => 0,
                    'end' => 100,
                ],
                [
                    'type' => 'slider',
                    'start' => 0,
                    'end' => 100,
                ],
            ],
        ];
    }

    /**
     * @param array<int, int|float> $data
     */
    private function movingAverage(array $data, int $window): array
    {
        $result = [];
        $count = count($data);

        for ($i = 0; $i < $count; ++$i) {
            $start = max(0, $i - $window + 1);
            $length = min($window, $i + 1);
            $subset = array_slice($data, $start, $length);
            $result[] = array_sum($subset) / count($subset);
        }

        return $result;
    }
}
