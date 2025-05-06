<?php

declare(strict_types=1);

namespace App\Domain\App\BuildTrainingMetricsHtml;

use App\Domain\Strava\Activity\ActivitiesEnricher;
use App\Domain\Strava\Activity\TrainingLoadChart;
use App\Domain\Strava\Activity\TrainingMetricsCalculator;
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

        // Get today's date using the command's current date time
        $todayDate = $now->format('Y-m-d');

        // Determine first and last date for the date range
        $dates = empty($dailyLoadData) ? [] : array_keys($dailyLoadData);
        if (!empty($dates)) {
            sort($dates);
            $firstDate = new \DateTime(reset($dates));

            // Use today's date as the last date to ensure we include all days up to today
            $lastDate = new \DateTime($todayDate);

            // Create complete date range from first activity to today
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

        // Sort dates chronologically
        uksort($dailyLoadData, function ($a, $b) {
            return strtotime($a) - strtotime($b);
        });

        // Determine first loaded date
        $dates = array_keys($dailyLoadData);
        if (empty($dates)) {
            // nothing to do
            return;
        }
        $firstDateStr = reset($dates);

        // --- Seed initial CTL/ATL using either stored history or a warm-up period ---
        $allDates = array_keys($dailyLoadData);
        // Warm-up: use up to 56 days of data immediately preceding the first calculation date
        $warmUpDays = 56;
        // Filter dates before the first calculation date
        $warmDates = array_filter($allDates, fn (string $d) => $d < $firstDateStr);
        // Take the last $warmUpDays days from that set
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
        // If no seed date determined (no prior metrics and no warm-up data), start at firstDate
        if (null === $seedDate) {
            $seedDate = $firstDateStr;
        }
        // --- Calculate CTL/ATL curve over the full dataset starting from seed ---
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
