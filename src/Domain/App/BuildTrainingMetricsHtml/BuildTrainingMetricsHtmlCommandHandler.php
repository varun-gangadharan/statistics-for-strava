<?php

declare(strict_types=1);

namespace App\Domain\App\BuildTrainingMetricsHtml;

use App\Domain\Strava\Activity\ActivitiesEnricher;
use App\Domain\Strava\Activity\Training\TrainingLoadChart;
use App\Domain\Strava\Activity\Training\TrainingMetricsCalculator;
use App\Domain\Strava\Athlete\AthleteRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Serialization\Json;
use League\Flysystem\FilesystemOperator;
use Twig\Environment;

final readonly class BuildTrainingMetricsHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivitiesEnricher $activitiesEnricher,
        private Environment $twig,
        private FilesystemOperator $buildStorage,
        private AthleteRepository $athleteRepository,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildTrainingMetricsHtml);

        $now = $command->getCurrentDateTime();
        $allActivities = $this->activitiesEnricher->getEnrichedActivities();

        $dailyLoadData = [];
        $athlete = $this->athleteRepository->find();

        foreach ($allActivities as $activity) {
            $date = $activity->getStartDate()->format('Y-m-d');

            if (!isset($dailyLoadData[$date])) {
                $dailyLoadData[$date] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
            }

            if ($activity->getMovingTimeInSeconds() > 0) {
                $trimp = TrainingMetricsCalculator::calculateTrimp($activity, $athlete);

                $dailyLoadData[$date]['trimp'] += $trimp;
                $dailyLoadData[$date]['duration'] += $activity->getMovingTimeInSeconds();
                $dailyLoadData[$date]['intensity'] += $activity->getMovingTimeInSeconds() *
                    ($trimp / ($activity->getMovingTimeInSeconds() / 60));
            }
        }

        $todayDate = $now->format('Y-m-d');
        $dates = empty($dailyLoadData) ? [] : array_keys($dailyLoadData);
        if (!empty($dates)) {
            sort($dates);
            $firstDate = new \DateTime(reset($dates));
            $lastDate = new \DateTime($todayDate);
            $period = new \DatePeriod(
                $firstDate,
                new \DateInterval('P1D'),
                $lastDate->modify('+1 day') // Include today
            );

            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                if (!isset($dailyLoadData[$dateStr])) {
                    $dailyLoadData[$dateStr] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
                }
            }
        }

        uksort($dailyLoadData, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });
        $dates = array_keys($dailyLoadData);
        if (empty($dates)) {
            return;
        }
        $firstDateStr = reset($dates);

        $allDates = array_keys($dailyLoadData);
        $warmUpDays = 56;
        $warmDates = array_filter($allDates, fn (string $d) => $d < $firstDateStr);
        $warmDates = array_slice(
            $warmDates,
            max(0, count($warmDates) - $warmUpDays)
        );
        $warmData = [];
        foreach ($warmDates as $d) {
            $warmData[$d] = $dailyLoadData[$d];
        }
        $seedDate = null;
        $seedCtl = 0.0;
        $seedAtl = 0.0;

        $warmMetrics = TrainingMetricsCalculator::calculateMetrics($warmData);
        $warmDaily = $warmMetrics['dailyMetrics'] ?? [];
        if (!empty($warmDaily)) {
            end($warmDaily);
            $seedDate = key($warmDaily);
            $vals = current($warmDaily);
            $seedCtl = $vals['ctl'];
            $seedAtl = $vals['atl'];
        }

        if (null === $seedDate) {
            $seedDate = $firstDateStr;
        }

        $metrics = TrainingMetricsCalculator::calculateMetrics(
            $dailyLoadData,
            [$seedDate => ['ctl' => $seedCtl, 'atl' => $seedAtl]],
            $seedDate
        );

        $this->buildStorage->write(
            'training-metrics.html',
            $this->twig->render('html/dashboard/training-metrics.html.twig', [
                'trainingLoadChart' => Json::encode(
                    TrainingLoadChart::fromDailyLoadData(
                        $dailyLoadData,
                        42,
                        7,
                        $metrics['dailyMetrics']
                    )->build()
                ),
                'currentCtl' => $metrics['currentCtl'],
                'currentAtl' => $metrics['currentAtl'],
                'currentTsb' => $metrics['currentTsb'],
                'acRatio' => $metrics['acRatio'],
                'restDaysLastWeek' => $metrics['restDaysLastWeek'],
                'monotony' => $metrics['monotony'],
                'strain' => $metrics['strain'],
                'weeklyTrimp' => $metrics['weeklyTrimp'],
                'lastUpdated' => $now->format('Y-m-d H:i:s'), // Add last updated timestamp
            ])
        );
    }
}
