<?php

declare(strict_types=1);

namespace App\Domain\Strava\Activity;

use App\Infrastructure\ValueObject\Time\SerializableDateTime;

final readonly class RelativeEffortChart
{
    /**
     * @param array<string, float> $relativeEffortData Date strings as keys, relative effort scores as values
     * @param array<string, string> $activityTypes Optional activity types for filtering
     */
    private function __construct(
        private array $relativeEffortData,
        private array $activityTypes = [],
    ) {
    }

    /**
     * @param array<string, float> $relativeEffortData Date strings as keys, relative effort scores as values
     * @param array<string, string> $activityTypes Optional activity types for categorization
     */
    public static function fromRelativeEffortData(array $relativeEffortData, array $activityTypes = []): self
    {
        return new self(
            relativeEffortData: $relativeEffortData,
            activityTypes: $activityTypes,
        );
    }

    /**
     * @return array<mixed>
     */
    public function build(): array
    {
        if (empty($this->relativeEffortData)) {
            return [];
        }

        // Sort data by date
        $dates = array_keys($this->relativeEffortData);
        sort($dates);

        // Format dates for display and prepare data
        $formattedDates = [];
        $relativeEffortValues = [];
        $activityTypeGroups = []; 
        $weeklyEffort = [];
        $allValues = array_values($this->relativeEffortData);

        // Calculate weekly sums
        $currentWeekKey = '';
        $currentWeekSum = 0;
        $weeklyKeys = [];

        foreach ($dates as $index => $date) {
            // Format date for display
            $dateObj = SerializableDateTime::fromString($date);
            $formattedDates[] = $dateObj->format('M d');
            
            // Group by week for weekly summary
            $weekKey = $dateObj->format('Y-W'); // Year and week number
            
            if ($currentWeekKey === '') {
                $currentWeekKey = $weekKey;
                $weeklyKeys[] = $dateObj->format('M d') . ' - ';
            } elseif ($currentWeekKey !== $weekKey) {
                $weeklyEffort[] = $currentWeekSum;
                $weeklyKeys[count($weeklyKeys) - 1] .= SerializableDateTime::fromString($dates[$index - 1])->format('M d');
                
                $currentWeekKey = $weekKey;
                $currentWeekSum = 0;
                $weeklyKeys[] = $dateObj->format('M d') . ' - ';
            }
            
            $currentWeekSum += $this->relativeEffortData[$date];
            
            // Get relative effort value
            $relativeEffortValues[] = $this->relativeEffortData[$date];
            
            // Group by activity type if provided
            if (isset($this->activityTypes[$date])) {
                $type = $this->activityTypes[$date];
                if (!isset($activityTypeGroups[$type])) {
                    $activityTypeGroups[$type] = array_fill(0, count($dates), null);
                }
                $activityTypeGroups[$type][$index] = $this->relativeEffortData[$date];
            }
        }
        
        // Add the last week
        if ($currentWeekSum > 0) {
            $weeklyEffort[] = $currentWeekSum;
            $weeklyKeys[count($weeklyKeys) - 1] .= SerializableDateTime::fromString(end($dates))->format('M d');
        }

        // Calculate min/max for y-axis with some padding
        $maxEffort = ceil(max($allValues) * 1.1);
        $weeklyMaxEffort = ceil(max($weeklyEffort) * 1.1);

        // Determine effort levels for visualization
        $highEffortThreshold = 100; // Highly subjective, adjust based on your data
        $moderateEffortThreshold = 50;
        
        // Prepare the series data for activity types
        $activityTypeSeries = [];
        foreach ($activityTypeGroups as $type => $values) {
            $activityTypeSeries[] = [
                'name' => $type,
                'type' => 'bar',
                'stack' => 'total',
                'data' => $values,
                'emphasis' => [
                    'focus' => 'series',
                ],
            ];
        }

        return [
            'tooltip' => [
                'trigger' => 'axis',
                'axisPointer' => [
                    'type' => 'shadow',
                ],
            ],
            'legend' => [
                'data' => array_merge(['Relative Effort'], array_keys($activityTypeGroups)),
                'selected' => array_merge(
                    ['Relative Effort' => empty($activityTypeGroups)],
                    array_fill_keys(array_keys($activityTypeGroups), true)
                ),
            ],
            'grid' => [
                [
                    'left' => '3%',
                    'right' => '3%',
                    'top' => '5%',
                    'height' => '35%',
                    'containLabel' => true,
                ],
                [
                    'left' => '3%',
                    'right' => '3%',
                    'top' => '55%',
                    'height' => '35%',
                    'containLabel' => true,
                ],
            ],
            'xAxis' => [
                [
                    'type' => 'category',
                    'data' => $formattedDates,
                    'axisLabel' => [
                        'interval' => count($formattedDates) > 30 ? ceil(count($formattedDates) / 30) : 0,
                        'rotate' => count($formattedDates) > 30 ? 45 : 0,
                    ],
                    'gridIndex' => 0,
                ],
                [
                    'type' => 'category',
                    'data' => $weeklyKeys,
                    'axisLabel' => [
                        'rotate' => 45,
                    ],
                    'gridIndex' => 1,
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'name' => 'Relative Effort',
                    'max' => $maxEffort,
                    'gridIndex' => 0,
                ],
                [
                    'type' => 'value',
                    'name' => 'Weekly Effort',
                    'max' => $weeklyMaxEffort,
                    'gridIndex' => 1,
                ],
            ],
            'visualMap' => [
                [
                    'show' => false,
                    'type' => 'piecewise',
                    'dimension' => 1,
                    'seriesIndex' => 0,
                    'pieces' => [
                        ['gt' => $highEffortThreshold, 'color' => '#EE6666'], // Red for high effort
                        ['gt' => $moderateEffortThreshold, 'lte' => $highEffortThreshold, 'color' => '#FAC858'], // Yellow for moderate
                        ['lte' => $moderateEffortThreshold, 'color' => '#91CC75'], // Green for low effort
                    ],
                ],
            ],
            'series' => array_merge(
                empty($activityTypeGroups) ? [
                    [
                        'name' => 'Relative Effort',
                        'data' => $relativeEffortValues,
                        'type' => 'bar',
                        'xAxisIndex' => 0,
                        'yAxisIndex' => 0,
                    ],
                ] : $activityTypeSeries,
                [
                    [
                        'name' => 'Weekly Effort',
                        'data' => $weeklyEffort,
                        'type' => 'bar',
                        'xAxisIndex' => 1,
                        'yAxisIndex' => 1,
                        'itemStyle' => [
                            'color' => '#5470C6',
                        ],
                        'markLine' => [
                            'silent' => true,
                            'lineStyle' => [
                                'color' => '#333',
                                'type' => 'dashed',
                            ],
                            'data' => [
                                [
                                    'type' => 'average',
                                    'name' => 'Average',
                                ],
                            ],
                        ],
                    ],
                ]
            ),
            'dataZoom' => [
                [
                    'type' => 'inside',
                    'xAxisIndex' => [0, 1],
                    'start' => max(0, 100 - (min(90, 500 / count($dates) * 100))),
                    'end' => 100,
                ],
                [
                    'type' => 'slider',
                    'xAxisIndex' => [0, 1],
                    'start' => max(0, 100 - (min(90, 500 / count($dates) * 100))),
                    'end' => 100,
                ],
            ],
        ];
    }
}

