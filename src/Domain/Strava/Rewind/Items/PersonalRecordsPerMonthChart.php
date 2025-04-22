<?php

declare(strict_types=1);

namespace App\Domain\Strava\Rewind\Items;

use App\Domain\Strava\Calendar\Month;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Infrastructure\ValueObject\Time\Year;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class PersonalRecordsPerMonthChart
{
    private function __construct(
        /** @var array<string, int> */
        private array $personalRecordsPerMonth,
        private Year $year,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param array<string, int> $personalRecordsPerMonth
     */
    public static function create(
        array $personalRecordsPerMonth,
        Year $year,
        TranslatorInterface $translator,
    ): self {
        return new self(
            personalRecordsPerMonth: $personalRecordsPerMonth,
            year: $year,
            translator: $translator
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $data = [];
        $xAxisLabels = [];
        for ($monthNumber = 1; $monthNumber <= 12; ++$monthNumber) {
            $monthAsString = sprintf('%s-%02d-01', $this->year, $monthNumber);
            $month = Month::fromDate(SerializableDateTime::fromString($monthAsString));
            $xAxisLabels[] = $month->getShortLabelWithoutYear();
            $data[] = $this->personalRecordsPerMonth[$monthAsString] ?? 0;
        }

        return [
            'animation' => true,
            'backgroundColor' => null,
            'tooltip' => [
                'trigger' => 'axis',
            ],
            'grid' => [
                'left' => '0%',
                'right' => '15px',
                'bottom' => '0%',
                'top' => '10px',
                'containLabel' => true,
            ],
            'xAxis' => [
                [
                    'type' => 'category',
                    'data' => $xAxisLabels,
                    'boundaryGap' => false,
                    'axisTick' => [
                        'show' => false,
                    ],
                    'splitLine' => [
                        'show' => false,
                    ],
                ],
            ],
            'yAxis' => [
                [
                    'type' => 'value',
                    'min' => 0,
                ],
            ],
            'series' => [
                [
                    'name' => $this->translator->trans('Personal Records'),
                    'color' => [
                        '#E34902',
                    ],
                    'label' => [
                        'show' => true,
                    ],
                    'type' => 'line',
                    'smooth' => false,
                    'lineStyle' => [
                        'width' => 2,
                    ],
                    'showSymbol' => true,
                    'data' => $data,
                ],
            ],
        ];
    }
}
