<?php

declare(strict_types=1);

namespace App\Domain\App\BuildTrainingMetricsHtml;

use App\Domain\Strava\Activity\ActivitiesEnricher;
use App\Domain\Strava\Activity\ActivityType;
use App\Domain\Strava\Activity\TrainingLoadChart;
use App\Domain\Strava\Activity\TrainingMetricsCalculator;
use App\Domain\Strava\Athlete\Athlete;
use App\Domain\Strava\Athlete\AthleteRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Serialization\Json;
use DateTime;
use DateInterval;
use DatePeriod;
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

        // Process activities and calculate daily TRIMP
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

        // Ensure complete date range without gaps
        $dates = array_keys($dailyLoadData);
        if (!empty($dates)) {
            sort($dates);
            $firstDate = new DateTime(reset($dates));
            $lastDate = new DateTime(end($dates));
            
            // Create complete date range
            $period = new DatePeriod(
                $firstDate,
                new DateInterval('P1D'),
                $lastDate->modify('+1 day')
            );

            foreach ($period as $date) {
                $dateStr = $date->format('Y-m-d');
                if (!isset($dailyLoadData[$dateStr])) {
                    $dailyLoadData[$dateStr] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
                }
            }
        }

        // Sort dates chronologically
        uksort($dailyLoadData, function($a, $b) {
            return strtotime($a) - strtotime($b);
        });

        // Use the shared calculator to get consistent metrics
        $metrics = TrainingMetricsCalculator::calculateMetrics($dailyLoadData);

        $this->buildStorage->write(
            'training-metrics.html',
            $this->twig->render('html/dashboard/training-metrics.html.twig', [
                'trainingLoadChart' => Json::encode(
                    TrainingLoadChart::fromDailyLoadData($dailyLoadData)->build(true)
                ),
                'currentCtl' => $metrics['currentCtl'],
                'currentAtl' => $metrics['currentAtl'],
                'currentTsb' => $metrics['currentTsb'],
                'acRatio' => $metrics['acRatio'],
                'restDaysLastWeek' => $metrics['restDaysLastWeek'],
                'monotony' => $metrics['monotony'],
                'strain' => $metrics['strain'],
                'weeklyTrimp' => $metrics['weeklyTrimp'],
            ])
        );
    }
}
