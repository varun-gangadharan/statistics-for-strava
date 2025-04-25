<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

final readonly class HeartRateVsPaceChart
{
    /**
     * @param array<int, int>   $heartRate Heart rate values in BPM
     * @param array<int, float> $pace      Pace values in min/km or min/mile
     * @param ?string           $paceUnit  Unit for pace display (e.g., 'min/km' or 'min/mile')
     * @param array<int, float> $elevation Optional elevation data for coloring
     */
    private function __construct(
        private array $heartRate,
        private array $pace,
        private ?string $paceUnit = 'min/km',
        private array $elevation = [],
    ) {
    }

    /**
     * @param array<int, int>   $heartRate Heart rate values in BPM
     * @param array<int, float> $pace      Pace values in min/km or min/mile
     * @param ?string           $paceUnit  Unit for pace display (e.g., 'min/km' or 'min/mile')
     * @param array<int, float> $elevation Optional elevation data for coloring
     */
    public static function fromActivityData(
        array $heartRate,
        array $pace,
        ?string $paceUnit = 'min/km',
        array $elevation = [],
    ): self {
        return new self(
            heartRate: $heartRate,
            pace: $pace,
            paceUnit: $paceUnit,
            elevation: $elevation,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        if (empty($this->heartRate) || empty($this->pace) || count($this->heartRate) !== count($this->pace)) {
            return [];
        }

        // Prepare scatter data points
        $data = [];
        $hasElevation = !empty($this->elevation) && count($this->elevation) === count($this->heartRate);

        // Calculate average values
        $avgHeartRate = array_sum($this->heartRate) / count($this->heartRate);
        $avgPace = array_sum($this->pace) / count($this->pace);

        // Calculate min/max values with padding
        $minHr = floor(min($this->heartRate) * 0.95);
        $maxHr = ceil(max($this->heartRate) * 1.05);

        $minPace = floor(min($this->pace) * 0.95);
        $maxPace = ceil(max($this->pace) * 1.05);

        // Prepare data points
        foreach (range(0, count($this->heartRate) - 1) as $i) {
            if ($hasElevation) {
                $data[] = [$this->heartRate[$i], $this->pace[$i], $this->elevation[$i]];
            } else {
                $data[] = [$this->heartRate[$i], $this->pace[$i]];
            }
        }

        // Calculate linear regression
        $regression = $this->calculateLinearRegression($this->heartRate, $this->pace);
        $regressionLine = $this->generateRegressionLine($regression, $minHr, $maxHr);

        // Generate chart configuration
        return [
            'tooltip' => [
                'trigger' => 'item',
                'formatter' => function ($params) {
                    $hr = $params['value'][0];
                    $pace = $params['value'][1];
                    $elevation = isset($params['value'][2]) ? $params['value'][2].'m' : '';

                    $formattedPace = $this->formatPace($pace);

                    return "HR: {$hr} BPM<br/>Pace: {$formattedPace}<br/>{$elevation}";
                },
            ],
            'grid' => [
                'left' => '5%',
                'right' => '5%',
                'bottom' => '10%',
                'top' => '10%',
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'value',
                'name' => 'Heart Rate (BPM)',
                'nameLocation' => 'middle',
                'nameGap' => 30,
                'min' => $minHr,
                'max' => $maxHr,
            ],
            'yAxis' => [
                'type' => 'value',
                'name' => 'Pace ('.$this->paceUnit.')',
                'nameLocation' => 'middle',
                'nameGap' => 30,
                'min' => $minPace,
                'max' => $maxPace,
                'axisLabel' => [
                    'formatter' => function ($value) {
                        return $this->formatPace($value);
                    },
                ],
            ],
            'series' => [
                [
                    'name' => 'HR vs Pace',
                    'type' => 'scatter',
                    'symbolSize' => 8,
                    'data' => $data,
                    'emphasis' => [
                        'itemStyle' => [
                            'shadowBlur' => 10,
                            'shadowColor' => 'rgba(0, 0, 0, 0.5)',
                        ],
                    ],
                ],
                [
                    'name' => 'Trend Line',
                    'type' => 'line',
                    'data' => $regressionLine,
                    'symbol' => 'none',
                    'lineStyle' => [
                        'type' => 'dashed',
                        'width' => 2,
                    ],
                    'markPoint' => [
                        'data' => [
                            [
                                'name' => 'Average',
                                'coord' => [$avgHeartRate, $avgPace],
                                'itemStyle' => [
                                    'color' => '#333',
                                ],
                                'symbolSize' => 12,
                                'label' => [
                                    'formatter' => 'Avg',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'visualMap' => $hasElevation ? [
                'show' => true,
                'dimension' => 2,
                'min' => min($this->elevation),
                'max' => max($this->elevation),
                'calculable' => true,
                'orient' => 'horizontal',
                'left' => 'center',
                'bottom' => '5%',
                'text' => ['Elevation Low', 'Elevation High'],
                'inRange' => [
                    'color' => ['#3CB371', '#FFD700', '#FF6347'], // Green to yellow to red
                ],
            ] : null,
        ];
    }

    /**
     * Format pace value as mm:ss.
     */
    private function formatPace(float $pace): string
    {
        $minutes = (int) $pace;
        $seconds = (int) (($pace - $minutes) * 60);

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    /**
     * Calculate linear regression coefficients.
     *
     * @param array<int, int>   $x X values (heart rate)
     * @param array<int, float> $y Y values (pace)
     *
     * @return array{slope: float, intercept: float, r2: float}
     */
    private function calculateLinearRegression(array $x, array $y): array
    {
        $n = count($x);
        if (0 === $n) {
            return ['slope' => 0, 'intercept' => 0, 'r2' => 0];
        }

        $sumX = array_sum($x);
        $sumY = array_sum($y);

        $sumXX = 0;
        $sumXY = 0;

        for ($i = 0; $i < $n; ++$i) {
            $sumXX += $x[$i] * $x[$i];
            $sumXY += $x[$i] * $y[$i];
        }

        $xMean = $sumX / $n;
        $yMean = $sumY / $n;

        $divideBy = $n * $sumXX - $sumX * $sumX;
        if($divideBy === 0){
            return ['slope' => 0, 'intercept' => 0, 'r2' => 0];
        }
        $slope = ($n * $sumXY - $sumX * $sumY) / $divideBy;
        $intercept = $yMean - $slope * $xMean;

        // Calculate R-squared
        $ssr = 0;
        $sst = 0;

        for ($i = 0; $i < $n; ++$i) {
            $predicted = $slope * $x[$i] + $intercept;
            $ssr += pow($y[$i] - $predicted, 2);
            $sst += pow($y[$i] - $yMean, 2);
        }

        $r2 = ($sst > 0) ? 1 - ($ssr / $sst) : 0;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r2' => $r2,
        ];
    }

    /**
     * Generate points for the regression line.
     *
     * @param array{slope: float, intercept: float, r2: float} $regression
     *
     * @return array<array{int, float}>
     */
    private function generateRegressionLine(array $regression, float $minX, float $maxX): array
    {
        $line = [];
        $slope = $regression['slope'];
        $intercept = $regression['intercept'];

        $line[] = [$minX, $slope * $minX + $intercept];
        $line[] = [$maxX, $slope * $maxX + $intercept];

        return $line;
    }
}
