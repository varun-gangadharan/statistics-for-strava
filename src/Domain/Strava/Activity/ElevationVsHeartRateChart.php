<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

final readonly class ElevationVsHeartRateChart
{
    /**
     * @param array<int, float> $elevation Elevation values in meters
     * @param array<int, int> $heartRate Heart rate values in BPM
     * @param array<int, float> $distance Optional distance data in km
     */
    private function __construct(
        private array $elevation,
        private array $heartRate,
        private array $distance = [],
    ) {
    }

    /**
     * @param array<int, float> $elevation Elevation values in meters
     * @param array<int, int> $heartRate Heart rate values in BPM
     * @param array<int, float> $distance Optional distance data in km
     */
    public static function fromActivityData(
        array $elevation,
        array $heartRate,
        array $distance = []
    ): self {
        return new self(
            elevation: $elevation,
            heartRate: $heartRate,
            distance: $distance,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        if (empty($this->elevation) || empty($this->heartRate) || count($this->elevation) !== count($this->heartRate)) {
            return [];
        }

        $hasDistance = !empty($this->distance) && count($this->distance) === count($this->heartRate);

        // Prepare the data for visualization
        $hrData = [];
        $elevationData = [];
        $xAxisData = [];
        
        if ($hasDistance) {
            $xAxisData = $this->distance;
            $xAxisName = 'Distance (km)';
        } else {
            // Use data point index as x-axis
            $xAxisData = range(0, count($this->heartRate) - 1);
            $xAxisName = 'Time (data points)';
        }

        // Calculate gradients for coloring
        $gradients = [];
        $elevationDiff = [];
        $distanceDiff = [];
        
        // Calculate elevation differences for gradient
        for ($i = 1; $i < count($this->elevation); $i++) {
            $elevationDiff[] = $this->elevation[$i] - $this->elevation[$i - 1];
            
            if ($hasDistance) {
                $distVal = max(0.001, $this->distance[$i] - $this->distance[$i - 1]); // Avoid division by zero
                $gradients[] = ($this->elevation[$i] - $this->elevation[$i - 1]) / ($distVal * 1000) * 100; // Convert to percentage
            } else {
                $gradients[] = $this->elevation[$i] - $this->elevation[$i - 1]; // Simpler version without distance
            }
        }
        
        // Add 0 for the first point to match array lengths
        array_unshift($gradients, 0);
        
        // Smooth heart rate and elevation data for better visualization - reduced for short runs
        $windowSize = count($this->heartRate) < 500 ? 2 : 5; // Smaller window for short runs (3-5km)
        $smoothedHeartRate = $this->movingAverage($this->heartRate, $windowSize);
        $smoothedElevation = $this->movingAverage($this->elevation, $windowSize);
        
        // Calculate correlation between elevation and heart rate
        $correlation = $this->calculateCorrelation($this->elevation, $this->heartRate);
        $correlationText = $this->interpretCorrelation($correlation);
        
        // Calculate average heart rate for uphill, flat, and downhill sections
        $uphillHr = [];
        $downhillHr = [];
        $flatHr = [];
        
        for ($i = 0; $i < count($gradients); $i++) {
            if ($gradients[$i] > 3) { // Uphill (>3% gradient)
                $uphillHr[] = $this->heartRate[$i];
            } elseif ($gradients[$i] < -3) { // Downhill (<-3% gradient)
                $downhillHr[] = $this->heartRate[$i];
            } else { // Flat (-3% to 3% gradient)
                $flatHr[] = $this->heartRate[$i];
            }
        }
        
        $avgUphillHr = !empty($uphillHr) ? round(array_sum($uphillHr) / count($uphillHr)) : 0;
        $avgDownhillHr = !empty($downhillHr) ? round(array_sum($downhillHr) / count($downhillHr)) : 0;
        $avgFlatHr = !empty($flatHr) ? round(array_sum($flatHr) / count($flatHr)) : 0;
        
        // Generate chart configuration
        return [
            'title' => [
                'text' => 'Elevation vs Heart Rate',
                'subtext' => "Correlation: {$correlation} ({$correlationText})",
                'left' => 'center',
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'cross',
                ],
                'formatter' => function ($params) use ($gradients) {
                    $result = '';
                    
                    foreach ($params as $param) {
                        if ($param['seriesName'] === 'Heart Rate') {
                            $result .= 'HR: ' . round($param['value']) . ' BPM<br/>';
                        } elseif ($param['seriesName'] === 'Elevation') {
                            $result .= 'Elevation: ' . round($param['value']) . ' m<br/>';
                        }
                        
                        // Add gradient info if available
                        $index = $param['dataIndex'];
                        if (isset($gradients[$index])) {
                            $gradient = round($gradients[$index], 1);
                            $result .= 'Gradient: ' . $gradient . '%<br/>';
                        }
                    }
                    
                    return $result;
                },
            ],
            'legend' => [
                'data' => ['Heart Rate', 'Elevation'],
                'top' => 30,
            ],
            'grid' => [
                'left' => '3%',
                'right' => '3%',
                'bottom' => '15%',
                'top' => 80,
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'category',
                'boundaryGap' => false,
                'data' => $xAxisData,
                'name' => $xAxisName,
                'nameLocation' => 'middle',
                'nameGap' => 30,
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'name' => 'Heart Rate (BPM)',
                    'min' => floor(min($this->heartRate) * 0.95),
                    'max' => ceil(max($this->heartRate) * 1.05),
                    'position' => 'left',
                ],
                [
                    'type' => 'value',
                    'name' => 'Elevation (m)',
                    'min' => floor(min($this->elevation) * 0.95),
                    'max' => ceil(max($this->elevation) * 1.05),
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
                'type' => 'continuous',
                'min' => min($gradients),
                'max' => max($gradients),
                'calculable' => true,
                'orient' => 'horizontal',
                'left' => 'center',
                'bottom' => '5%',
                'dimension' => 0,
                'text' => ['Downhill', 'Uphill'],
                'seriesIndex' => 0,
                'inRange' => [
                    'color' => ['#3CB371', '#FFD700', '#FF6347'], // Green (downhill) to yellow (flat) to red (uphill)
                ],
            ],
            'series' => [
                [
                    'name' => 'Heart Rate',
                    'type' => 'line',
                    'data' => $smoothedHeartRate,
                    'smooth' => true,
                    'yAxisIndex' => 0,
                    'lineStyle' => [
                        'width' => 3,
                        'color' => '#FC4C02', // Strava orange
                    ],
                    'markLine' => [
                        'silent' => true,
                        'lineStyle' => [
                            'color' => '#999',
                            'type' => 'dashed',
                        ],
                        'data' => [
                            [
                                'type' => 'average',
                                'name' => 'Average HR',
                            ],
                        ],
                        'label' => [
                            'formatter' => 'Avg: {c} BPM',
                        ],
                    ],
                ],
                [
                    'name' => 'Elevation',
                    'type' => 'line',
                    'data' => $smoothedElevation,
                    'smooth' => true,
                    'yAxisIndex' => 1,
                    'lineStyle' => [
                        'width' => 3,
                        'color' => '#5470C6', // Blue
                    ],
                    'areaStyle' => [
                        'color' => [
                            'type' => 'linear',
                            'x' => 0, 'y' => 0, 'x2' => 0, 'y2' => 1,
                            'colorStops' => [
                                ['offset' => 0, 'color' => 'rgba(84, 112, 198, 0.5)'],
                                ['offset' => 1, 'color' => 'rgba(84, 112, 198, 0.1)'],
                            ],
                        ],
                    ],
                ],
            ],
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
            'graphic' => [
                [
                    'type' => 'group',
                    'left' => 'center',
                    'top' => 40,
                    'children' => [
                        [
                            'type' => 'text',
                            'style' => [
                                'text' => "Uphill HR: {$avgUphillHr} BPM | Flat HR: {$avgFlatHr} BPM | Downhill HR: {$avgDownhillHr} BPM",
                                'font' => '12px Arial',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Calculate Pearson correlation coefficient
     * 
     * @param array<int, float> $x Elevation data
     * @param array<int, int> $y Heart rate data
     */
    private function calculateCorrelation(array $x, array $y): float
    {
        $n = count($x);
        
        // Calculate means
        $meanX = array_sum($x) / $n;
        $meanY = array_sum($y) / $n;
        
        // Calculate covariance and standard deviations
        $covariance = 0;
        $stdDevX = 0;
        $stdDevY = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $covariance += ($x[$i] - $meanX) * ($y[$i] - $meanY);
            $stdDevX += pow($x[$i] - $meanX, 2);
            $stdDevY += pow($y[$i] - $meanY, 2);
        }
        
        $stdDevX = sqrt($stdDevX / $n);
        $stdDevY = sqrt($stdDevY / $n);
        
        // Calculate correlation
        if ($stdDevX == 0 || $stdDevY == 0) {
            return 0; // No variance, so no correlation
        }
        
        $correlation = $covariance / ($n * $stdDevX * $stdDevY);
        
        return round($correlation, 3);
    }
    
    /**
     * Interpret correlation value
     */
    private function interpretCorrelation(float $correlation): string
    {
        $abs = abs($correlation);
        
        if ($abs < 0.1) {
            return 'Negligible';
        } elseif ($abs < 0.3) {
            return 'Weak';
        } elseif ($abs < 0.5) {
            return 'Moderate';
        } elseif ($abs < 0.7) {
            return 'Strong';
        } else {
            return 'Very Strong';
        }
    }
    
    /**
     * @param array<int, int|float> $data
     */
    private function movingAverage(array $data, int $window): array
    {
        $result = [];
        $count = count($data);
        
        for ($i = 0; $i < $count; $i++) {
            $start = max(0, $i - $window + 1);
            $length = min($window, $i + 1);
            $subset = array_slice($data, $start, $length);
            $result[] = array_sum($subset) / count($subset);
        }
        
        return $result;
    }
}
