<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

final readonly class PolarizedTrainingDistributionChart
{
    private const ZONE_1_LABEL = 'Zone 1 (Easy)';
    private const ZONE_2_LABEL = 'Zone 2 (Moderate)';
    private const ZONE_3_LABEL = 'Zone 3 (Threshold)';
    private const ZONE_4_LABEL = 'Zone 4 (Hard)';
    private const ZONE_5_LABEL = 'Zone 5 (Max)';

    /**
     * @param array<string, array<string, int>> $trainingData  Activity dates as keys, with duration in seconds per zone
     * @param array<string, string>             $activityTypes Activity types for optional filtering
     * @param string                            $periodType    'weekly', 'monthly', or 'yearly'
     */
    private function __construct(
        private array $trainingData,
        private array $activityTypes = [],
        private string $periodType = 'weekly',
    ) {
    }

    /**
     * @param array<string, array<string, int>> $trainingData  Activity dates as keys, with duration in seconds per zone
     * @param array<string, string>             $activityTypes Activity types for optional filtering
     * @param string                            $periodType    'weekly', 'monthly', or 'yearly'
     */
    public static function fromTrainingZoneData(
        array $trainingData,
        array $activityTypes = [],
        string $periodType = 'weekly',
    ): self {
        return new self(
            trainingData: $trainingData,
            activityTypes: $activityTypes,
            periodType: $periodType,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        if (empty($this->trainingData)) {
            return [];
        }

        // Prepare data for chart
        $dates = array_keys($this->trainingData);
        sort($dates);

        // Group data by period (week, month, or year)
        $periodData = [];
        $periodLabels = [];

        foreach ($dates as $date) {
            $periodKey = $this->getPeriodKey($date);

            if (!isset($periodData[$periodKey])) {
                $periodData[$periodKey] = [
                    self::ZONE_1_LABEL => 0,
                    self::ZONE_2_LABEL => 0,
                    self::ZONE_3_LABEL => 0,
                    self::ZONE_4_LABEL => 0,
                    self::ZONE_5_LABEL => 0,
                ];
                $periodLabels[] = $periodKey;
            }

            // Only include activities of specified types if provided
            if (!empty($this->activityTypes) && !isset($this->activityTypes[$date])) {
                continue;
            }

            // Add time in each zone
            foreach ($this->trainingData[$date] as $zone => $seconds) {
                $zoneName = $this->getZoneName($zone);
                $periodData[$periodKey][$zoneName] += $seconds;
            }
        }

        // Convert seconds to hours for display
        foreach ($periodData as $period => $zones) {
            foreach ($zones as $zone => $seconds) {
                $periodData[$period][$zone] = round($seconds / 3600, 1); // Convert to hours
            }
        }

        // Prepare series data
        $series = [];
        $zoneNames = [self::ZONE_1_LABEL, self::ZONE_2_LABEL, self::ZONE_3_LABEL, self::ZONE_4_LABEL, self::ZONE_5_LABEL];

        foreach ($zoneNames as $zone) {
            $data = [];
            foreach ($periodLabels as $period) {
                $data[] = $periodData[$period][$zone];
            }

            $series[] = [
                'name' => $zone,
                'type' => 'bar',
                'stack' => 'total',
                'emphasis' => [
                    'focus' => 'series',
                ],
                'data' => $data,
            ];
        }

        // Calculate polarization metrics
        $totalTimeInZones = [];
        $polarizationRatios = [];

        foreach ($periodLabels as $period) {
            $zone1 = $periodData[$period][self::ZONE_1_LABEL];
            $zone2 = $periodData[$period][self::ZONE_2_LABEL];
            $zone3 = $periodData[$period][self::ZONE_3_LABEL];
            $zone4 = $periodData[$period][self::ZONE_4_LABEL];
            $zone5 = $periodData[$period][self::ZONE_5_LABEL];

            $totalTime = $zone1 + $zone2 + $zone3 + $zone4 + $zone5;
            $totalTimeInZones[] = $totalTime;

            // Calculate 80/20 ratio (Low:High intensity)
            $lowIntensity = $zone1 + $zone2;
            $highIntensity = $zone4 + $zone5;

            // Avoid division by zero
            if ($totalTime > 0) {
                $lowPercentage = ($lowIntensity / $totalTime) * 100;
                $highPercentage = ($highIntensity / $totalTime) * 100;
                $polarizationRatios[] = round($lowPercentage, 1).'/'.round($highPercentage, 1);
            } else {
                $polarizationRatios[] = 'N/A';
            }
        }

        // Check if the training is properly polarized (80/20 rule)
        $isPolarized = [];
        foreach ($periodLabels as $index => $period) {
            if (!isset($totalTimeInZones[$index]) || 0 == $totalTimeInZones[$index]) {
                $isPolarized[] = false;
                continue;
            }

            $zone1 = $periodData[$period][self::ZONE_1_LABEL];
            $zone2 = $periodData[$period][self::ZONE_2_LABEL];
            $zone4 = $periodData[$period][self::ZONE_4_LABEL];
            $zone5 = $periodData[$period][self::ZONE_5_LABEL];

            $lowIntensity = $zone1 + $zone2;
            $highIntensity = $zone4 + $zone5;
            $lowPercentage = ($lowIntensity / $totalTimeInZones[$index]) * 100;
            $highPercentage = ($highIntensity / $totalTimeInZones[$index]) * 100;

            // Is it roughly 80/20?
            $isPolarized[] = ($lowPercentage >= 75 && $lowPercentage <= 85
                              && $highPercentage >= 15 && $highPercentage <= 25);
        }

        return [
            'title' => [
                'text' => 'Polarized Training Distribution',
                'subtext' => 'Time spent in each heart rate zone',
                'left' => 'center',
            ],
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'shadow',
                ],
                'formatter' => function ($params) use ($polarizationRatios) {
                    $result = $params[0]['axisValue'].'<br/>';
                    $total = 0;

                    foreach ($params as $param) {
                        $result .= $param['marker'].' '.$param['seriesName'].': '.$param['value'].' hours<br/>';
                        $total += $param['value'];
                    }

                    $result .= '<b>Total: '.round($total, 1).' hours</b><br/>';
                    $result .= 'Low/High Ratio: '.$polarizationRatios[$params[0]['dataIndex']];

                    return $result;
                },
            ],
            'legend' => [
                'data' => $zoneNames,
            ],
            'grid' => [
                'left' => '3%',
                'right' => '4%',
                'bottom' => '3%',
                'containLabel' => true,
            ],
            'xAxis' => [
                'type' => 'category',
                'data' => $periodLabels,
            ],
            'yAxis' => [
                'type' => 'value',
                'name' => 'Hours',
            ],
            'series' => $series,
            'color' => [
                '#91CC75', // Green - Zone 1
                '#FAC858', // Yellow - Zone 2
                '#EE6666', // Red - Zone 3
                '#9966CC', // Purple - Zone 4
                '#5470C6', // Blue - Zone 5
            ],
            'graphic' => array_map(function ($index, $isPolarized) use ($periodLabels) {
                if (!$isPolarized) {
                    return null;
                }

                $xCoord = $index / (count($periodLabels) - 1) * 100;

                return [
                    'type' => 'text',
                    'left' => $xCoord.'%',
                    'top' => '10%',
                    'style' => [
                        'text' => '✓',
                        'fontSize' => 16,
                        'fontWeight' => 'bold',
                        'fill' => '#91CC75',
                        'stroke' => '#333',
                        'lineWidth' => 0,
                    ],
                ];
            }, array_keys($isPolarized), $isPolarized),
        ];
    }

    /**
     * Get period key based on date and period type.
     */
    private function getPeriodKey(string $date): string
    {
        $dateTime = new \DateTime($date);

        if ('weekly' === $this->periodType) {
            $weekNumber = $dateTime->format('W');
            $year = $dateTime->format('Y');

            return "W{$weekNumber}, {$year}";
        } elseif ('monthly' === $this->periodType) {
            return $dateTime->format('M Y');
        } else { // yearly
            return $dateTime->format('Y');
        }
    }

    /**
     * Convert zone identifier to human-readable name.
     */
    private function getZoneName(string $zone): string
    {
        // Map from API zone format to display format
        $zoneMap = [
            'zone_1' => self::ZONE_1_LABEL,
            'zone_2' => self::ZONE_2_LABEL,
            'zone_3' => self::ZONE_3_LABEL,
            'zone_4' => self::ZONE_4_LABEL,
            'zone_5' => self::ZONE_5_LABEL,
            'zone1' => self::ZONE_1_LABEL,
            'zone2' => self::ZONE_2_LABEL,
            'zone3' => self::ZONE_3_LABEL,
            'zone4' => self::ZONE_4_LABEL,
            'zone5' => self::ZONE_5_LABEL,
            '1' => self::ZONE_1_LABEL,
            '2' => self::ZONE_2_LABEL,
            '3' => self::ZONE_3_LABEL,
            '4' => self::ZONE_4_LABEL,
            '5' => self::ZONE_5_LABEL,
        ];

        return $zoneMap[$zone] ?? self::ZONE_1_LABEL;
    }
}
