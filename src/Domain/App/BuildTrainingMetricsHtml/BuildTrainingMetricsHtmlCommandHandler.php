<?php

declare(strict_types=1);

namespace App\Domain\App\BuildTrainingMetricsHtml;

use App\Domain\Strava\Activity\ActivitiesEnricher;
use App\Domain\Strava\Activity\TrainingLoadChart;
use App\Domain\Strava\Athlete\Athlete;
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

        $allActivitiesByDate = [];
        $dailyLoadData = [];

        $athlete = $this->athleteRepository->find();

        // Prepare activity data grouped by date
        foreach ($allActivities as $activity) {
            $date = $activity->getStartDate()->format('Y-m-d');
            if (!isset($allActivitiesByDate[$date])) {
                $allActivitiesByDate[$date] = [];
            }
            $allActivitiesByDate[$date][] = $activity;

            // Calculate TRIMP (Training Impulse) based on duration and heart rate
            if ($activity->getAverageHeartRate() && $activity->getMovingTimeInSeconds() > 0) {
                // Use the actual athlete age and configured Max Heart Rate formula
                $activityDate = $activity->getStartDate();
                $maxHr = $athlete->getMaxHeartRate($activityDate);

                $intensity = $activity->getAverageHeartRate() / $maxHr;
                $trimp = ($activity->getMovingTimeInSeconds() / 60) * $intensity * 1.92 * exp(1.67 * $intensity);

                if (!isset($dailyLoadData[$date])) {
                    $dailyLoadData[$date] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
                }

                $dailyLoadData[$date]['trimp'] += $trimp;
                $dailyLoadData[$date]['duration'] += $activity->getMovingTimeInSeconds();
                $dailyLoadData[$date]['intensity'] += $activity->getMovingTimeInSeconds() * $intensity;
            }
        }

        // Calculate additional metrics
        $today = $now->format('Y-m-d');
        $dates = array_keys($dailyLoadData);
        sort($dates);

        // Fill in days with no activities
        $lastDate = end($dates);
        $currentDate = reset($dates);
        while ($currentDate <= $lastDate) {
            if (!isset($dailyLoadData[$currentDate])) {
                $dailyLoadData[$currentDate] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
            }
            $currentDate = date('Y-m-d', strtotime($currentDate.' +1 day'));
        }

        // Calculate current metrics
        $currentCtl = 0;
        $currentAtl = 0;
        $weeklyTrimp = 0;
        $restDaysLastWeek = 0;
        $monotony = 0;
        $strain = 0;

        if (!empty($dailyLoadData)) {
            // Calculate CTL and ATL
            $ctlDays = 42; // ~6 weeks for Chronic Training Load
            $atlDays = 7;  // 7 days for Acute Training Load

            $dates = array_keys($dailyLoadData);
            sort($dates);
            $lastIndex = count($dates) - 1;

            if ($lastIndex >= 0) {
                // Calculate CTL (Chronic Training Load) - 42 day exponentially weighted average
                $ctlStartIndex = max(0, $lastIndex - $ctlDays + 1);
                $ctlWindow = array_slice(array_values($dailyLoadData), $ctlStartIndex, $ctlDays);
                $ctlSum = array_sum(array_column($ctlWindow, 'trimp'));
                $currentCtl = $ctlSum / count($ctlWindow);

                // Calculate ATL (Acute Training Load) - 7 day exponentially weighted average
                $atlStartIndex = max(0, $lastIndex - $atlDays + 1);
                $atlWindow = array_slice(array_values($dailyLoadData), $atlStartIndex, $atlDays);
                $atlSum = array_sum(array_column($atlWindow, 'trimp'));
                $weeklyTrimp = $atlSum;
                $currentAtl = $atlSum / count($atlWindow);

                // Calculate rest days in last week
                $lastWeekDates = array_slice($dates, -7);
                foreach ($lastWeekDates as $date) {
                    if (0 == $dailyLoadData[$date]['trimp']) {
                        ++$restDaysLastWeek;
                    }
                }

                // Calculate monotony (ratio of daily average to standard deviation) - 7 day calculation
                $lastWeekTrimp = array_column(array_intersect_key($dailyLoadData, array_flip($lastWeekDates)), 'trimp');
                $avgDailyLoad = array_sum($lastWeekTrimp) / 7;

                if ($avgDailyLoad > 0) {
                    $variance = 0;
                    foreach ($lastWeekTrimp as $trimp) {
                        $variance += pow($trimp - $avgDailyLoad, 2);
                    }
                    $stdDev = sqrt($variance / 7);
                    $monotony = ($stdDev > 0) ? $avgDailyLoad / $stdDev : 0;

                    // Calculate strain (weekly load * monotony) - 7 day calculation
                    $strain = array_sum($lastWeekTrimp) * $monotony;
                }
            }
        }

        $currentTsb = $currentCtl - $currentAtl; // Training Stress Balance
        $acRatio = ($currentCtl > 0) ? $currentAtl / $currentCtl : 0; // Acute:Chronic ratio

        $this->buildStorage->write(
            'training-metrics.html',
            $this->twig->render('html/dashboard/training-metrics.html.twig', [
                'trainingLoadChart' => Json::encode(
                    TrainingLoadChart::fromDailyLoadData($dailyLoadData)->build(true) // Show 4 months history + 1 month projection
                ),
                'currentCtl' => round($currentCtl, 1),
                'currentAtl' => round($currentAtl, 1),
                'currentTsb' => round($currentTsb, 1),
                'acRatio' => round($acRatio, 2),
                'restDaysLastWeek' => $restDaysLastWeek,
                'monotony' => round($monotony, 2),
                'strain' => round($strain, 0),
                'weeklyTrimp' => round($weeklyTrimp, 0),
            ])
        );
    }
}
