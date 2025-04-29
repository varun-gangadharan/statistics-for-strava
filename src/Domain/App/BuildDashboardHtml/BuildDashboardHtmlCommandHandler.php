<?php

declare(strict_types=1);

namespace App\Domain\App\BuildDashboardHtml;

use App\Domain\Strava\Activity\ActivitiesEnricher;
use App\Domain\Strava\Activity\ActivityIntensity;
use App\Domain\Strava\Activity\ActivityIntensityChart;
use App\Domain\Strava\Activity\ActivityTotals;
use App\Domain\Strava\Activity\ActivityType;
use App\Domain\Strava\Activity\ActivityTypeRepository;
use App\Domain\Strava\Activity\BestEffort\ActivityBestEffortRepository;
use App\Domain\Strava\Activity\BestEffort\BestEffortChart;
use App\Domain\Strava\Activity\DaytimeStats\DaytimeStats;
use App\Domain\Strava\Activity\DaytimeStats\DaytimeStatsCharts;
use App\Domain\Strava\Activity\DistanceBreakdown;
use App\Domain\Strava\Activity\SportType\SportTypeRepository;
use App\Domain\Strava\Activity\SportType\SportTypes;
use App\Domain\Strava\Activity\Stream\ActivityHeartRateRepository;
use App\Domain\Strava\Activity\Stream\ActivityPowerRepository;
use App\Domain\Strava\Activity\Stream\BestPowerOutputs;
use App\Domain\Strava\Activity\Stream\PowerOutputChart;
use App\Domain\Strava\Activity\TrainingLoadChart;
use App\Domain\Strava\Activity\TrainingMetricsCalculator;
use App\Domain\Strava\Activity\WeekdayStats\WeekdayStats;
use App\Domain\Strava\Activity\WeekdayStats\WeekdayStatsChart;
use App\Domain\Strava\Activity\WeeklyDistanceTimeChart;
use App\Domain\Strava\Activity\YearlyDistance\YearlyDistanceChart;
use App\Domain\Strava\Activity\YearlyDistance\YearlyStatistics;
use App\Domain\Strava\Athlete\AthleteRepository;
use App\Domain\Strava\Athlete\HeartRateZone;
use App\Domain\Strava\Athlete\TimeInHeartRateZoneChart;
use App\Domain\Strava\Athlete\Weight\AthleteWeightRepository;
use App\Domain\Strava\Calendar\Months;
use App\Domain\Strava\CarbonSavedComparison;
use App\Domain\Strava\Challenge\Consistency\ChallengeConsistency;
use App\Domain\Strava\Ftp\FtpHistoryChart;
use App\Domain\Strava\Ftp\FtpRepository;
use App\Domain\Strava\Trivia;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\Serialization\Json;
use App\Infrastructure\ValueObject\Measurement\UnitSystem;
use App\Infrastructure\ValueObject\Time\DateRange;
use App\Infrastructure\ValueObject\Time\Years;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildDashboardHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private ActivityHeartRateRepository $activityHeartRateRepository,
        private ActivityPowerRepository $activityPowerRepository,
        private FtpRepository $ftpRepository,
        private AthleteRepository $athleteRepository,
        private AthleteWeightRepository $athleteWeightRepository,
        private ActivityTypeRepository $activityTypeRepository,
        private SportTypeRepository $sportTypeRepository,
        private ActivityBestEffortRepository $activityBestEffortRepository,
        private ActivitiesEnricher $activitiesEnricher,
        private ActivityIntensity $activityIntensity,
        private UnitSystem $unitSystem,
        private Environment $twig,
        private FilesystemOperator $buildStorage,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildDashboardHtml);

        $now = $command->getCurrentDateTime();
        $athlete = $this->athleteRepository->find();
        $importedActivityTypes = $this->activityTypeRepository->findAll();
        $importedSportTypes = $this->sportTypeRepository->findAll();
        $allActivities = $this->activitiesEnricher->getEnrichedActivities();
        $activitiesPerActivityType = $this->activitiesEnricher->getActivitiesPerActivityType();
        $allFtps = $this->ftpRepository->findAll();
        $allYears = Years::create(
            startDate: $allActivities->getFirstActivityStartDate(),
            endDate: $now
        );
        $allMonths = Months::create(
            startDate: $allActivities->getFirstActivityStartDate(),
            now: $now
        );
        $weekdayStats = WeekdayStats::create(
            activities: $allActivities,
            translator: $this->translator
        );
        $dayTimeStats = DaytimeStats::create($allActivities);

        $weeklyDistanceTimeCharts = [];
        $distanceBreakdowns = [];
        $yearlyDistanceCharts = [];
        $yearlyStatistics = [];

        foreach ($activitiesPerActivityType as $activityType => $activities) {
            if ($activities->isEmpty()) {
                continue;
            }

            $activityType = ActivityType::from($activityType);
            if ($activityType->supportsWeeklyStats() && $chartData = WeeklyDistanceTimeChart::create(
                activities: $activitiesPerActivityType[$activityType->value],
                unitSystem: $this->unitSystem,
                translator: $this->translator,
                now: $now,
            )->build()) {
                $weeklyDistanceTimeCharts[$activityType->value] = Json::encode($chartData);
            }

            if ($activityType->supportsDistanceBreakdownStats()) {
                $distanceBreakdown = DistanceBreakdown::create(
                    activities: $activitiesPerActivityType[$activityType->value],
                    unitSystem: $this->unitSystem
                );

                if ($build = $distanceBreakdown->build()) {
                    $distanceBreakdowns[$activityType->value] = $build;
                }
            }

            if ($activityType->supportsYearlyStats()) {
                $yearlyDistanceCharts[$activityType->value] = Json::encode(
                    YearlyDistanceChart::create(
                        activities: $activitiesPerActivityType[$activityType->value],
                        unitSystem: $this->unitSystem,
                        translator: $this->translator,
                        now: $now
                    )->build()
                );

                $yearlyStatistics[$activityType->value] = YearlyStatistics::create(
                    activities: $activitiesPerActivityType[$activityType->value],
                    years: $allYears
                );
            }
        }

        /** @var \App\Domain\Strava\Ftp\Ftp $ftp */
        foreach ($allFtps as $ftp) {
            try {
                $ftp->enrichWithAthleteWeight(
                    $this->athleteWeightRepository->find($ftp->getSetOn())->getWeightInKg()
                );
            } catch (EntityNotFound) {
            }
        }

        $activityTotals = ActivityTotals::getInstance(
            activities: $allActivities,
            now: $now,
            translator: $this->translator,
        );
        $trivia = Trivia::getInstance($allActivities);
        $bestAllTimePowerOutputs = $this->activityPowerRepository->findBestForSportTypes(SportTypes::thatSupportPeakPowerOutputs());

        $bestEffortsCharts = [];
        /** @var ActivityType $activityType */
        foreach ($importedActivityTypes as $activityType) {
            if (!$activityType->supportsBestEffortsStats()) {
                continue;
            }

            $bestEffortsForActivityType = $this->activityBestEffortRepository->findBestEffortsFor($activityType);
            if ($bestEffortsForActivityType->isEmpty()) {
                continue;
            }

            $bestEffortsCharts[$activityType->value] = Json::encode(
                BestEffortChart::create(
                    activityType: $activityType,
                    bestEfforts: $bestEffortsForActivityType,
                    sportTypes: $importedSportTypes,
                    translator: $this->translator,
                )->build()
            );
        }

        $allActivitiesByDate = [];
        $dailyLoadData = [];

        // Prepare activity data grouped by date
        foreach ($allActivities as $activity) {
            $date = $activity->getStartDate()->format('Y-m-d');
            if (!isset($allActivitiesByDate[$date])) {
                $allActivitiesByDate[$date] = [];
            }
            $allActivitiesByDate[$date][] = $activity;

            if (!isset($dailyLoadData[$date])) {
                $dailyLoadData[$date] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
            }

            // Calculate training load using the shared calculator
            if ($activity->getMovingTimeInSeconds() > 0) {
                $trimp = TrainingMetricsCalculator::calculateTrimp($activity, $athlete);

                $dailyLoadData[$date]['trimp'] += $trimp;
                $dailyLoadData[$date]['duration'] += $activity->getMovingTimeInSeconds();
                $dailyLoadData[$date]['intensity'] += $activity->getMovingTimeInSeconds() *
                    ($trimp / ($activity->getMovingTimeInSeconds() / 60));
            }
        }

        // Fill in days with no activities
        $dates = array_keys($dailyLoadData);
        if (!empty($dates)) {
            sort($dates);
            $lastDate = end($dates);
            $currentDate = reset($dates);

            while ($currentDate <= $lastDate) {
                if (!isset($dailyLoadData[$currentDate])) {
                    $dailyLoadData[$currentDate] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
                }
                $currentDate = date('Y-m-d', strtotime($currentDate.' +1 day'));
            }
        }

        // Calculate metrics using the shared calculator
        $metrics = TrainingMetricsCalculator::calculateMetrics($dailyLoadData);

        // Build the training metrics page first
        $trainingLoadChart = TrainingLoadChart::fromDailyLoadData($dailyLoadData);

        $this->buildStorage->write(
            'training-metrics.html',
            $this->twig->render('html/dashboard/training-metrics.html.twig', [
                'trainingLoadChart' => Json::encode(
                    $trainingLoadChart->build(true)
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

        $this->buildStorage->write(
            'dashboard.html',
            $this->twig->load('html/dashboard/dashboard.html.twig')->render([
                'timeIntervals' => ActivityPowerRepository::TIME_INTERVALS_IN_SECONDS_REDACTED,
                'mostRecentActivities' => $allActivities->slice(0, 5),
                'intro' => $activityTotals,
                'weeklyDistanceCharts' => $weeklyDistanceTimeCharts,
                'powerOutputs' => $bestAllTimePowerOutputs,
                'activityIntensityChart' => Json::encode(
                    ActivityIntensityChart::create(
                        activities: $allActivities,
                        activityIntensity: $this->activityIntensity,
                        translator: $this->translator,
                        now: $now,
                    )->build()
                ),
                'weekdayStatsChart' => Json::encode(
                    WeekdayStatsChart::create($weekdayStats)->build(),
                ),
                'weekdayStats' => $weekdayStats,
                'daytimeStatsChart' => Json::encode(
                    DaytimeStatsCharts::create(
                        daytimeStats: $dayTimeStats,
                        translator: $this->translator,
                    )->build(),
                ),
                'daytimeStats' => $dayTimeStats,
                'distanceBreakdowns' => $distanceBreakdowns,
                'trivia' => $trivia,
                'ftpHistoryChart' => !$allFtps->isEmpty() ? Json::encode(
                    FtpHistoryChart::create(
                        ftps: $allFtps,
                        now: $now
                    )->build()
                ) : null,
                'timeInHeartRateZoneChart' => Json::encode(
                    TimeInHeartRateZoneChart::create(
                        timeInSecondsInHeartRateZoneOne: $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZone(HeartRateZone::ONE),
                        timeInSecondsInHeartRateZoneTwo: $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZone(HeartRateZone::TWO),
                        timeInSecondsInHeartRateZoneThree: $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZone(HeartRateZone::THREE),
                        timeInSecondsInHeartRateZoneFour: $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZone(HeartRateZone::FOUR),
                        timeInSecondsInHeartRateZoneFive: $this->activityHeartRateRepository->findTotalTimeInSecondsInHeartRateZone(HeartRateZone::FIVE),
                        translator: $this->translator,
                    )->build(),
                ),
                'challengeConsistency' => ChallengeConsistency::create(
                    months: $allMonths,
                    activities: $allActivities
                ),
                'yearlyDistanceCharts' => $yearlyDistanceCharts,
                'yearlyStatistics' => $yearlyStatistics,
                'bestEffortsCharts' => $bestEffortsCharts,
                // Training load metrics for the dashboard - same values as in the training-metrics.html
                'currentCtl' => $metrics['currentCtl'],
                'currentAtl' => $metrics['currentAtl'],
                'currentTsb' => $metrics['currentTsb'],
                'restDaysLastWeek' => $metrics['restDaysLastWeek'],
                'acRatio' => $metrics['acRatio'],
                'monotony' => $metrics['monotony'],
                'strain' => $metrics['strain'],
            ]),
        );

        $this->buildStorage->write(
            'carbon-comparison.html',
            $this->twig->load('html/dashboard/carbon-comparison.html.twig')->render([
                'carbonSavedComparison' => CarbonSavedComparison::create($trivia->getTotalCarbonSaved()),
                'carbonSavedInKg' => $trivia->getTotalCarbonSaved(),
            ]),
        );

        if ($bestAllTimePowerOutputs->isEmpty()) {
            return;
        }

        $bestPowerOutputs = BestPowerOutputs::empty();
        $bestPowerOutputs->add(
            description: $this->translator->trans('All time'),
            powerOutputs: $bestAllTimePowerOutputs
        );
        $bestPowerOutputs->add(
            description: $this->translator->trans('Last 45 days'),
            powerOutputs: $this->activityPowerRepository->findBestForSportTypesInDateRange(
                sportTypes: SportTypes::thatSupportPeakPowerOutputs(),
                dateRange: DateRange::lastXDays($now, 45)
            )
        );
        $bestPowerOutputs->add(
            description: $this->translator->trans('Last 90 days'),
            powerOutputs: $this->activityPowerRepository->findBestForSportTypesInDateRange(
                sportTypes: SportTypes::thatSupportPeakPowerOutputs(),
                dateRange: DateRange::lastXDays($now, 90)
            )
        );
        foreach ($allYears->reverse() as $year) {
            $bestPowerOutputs->add(
                description: (string) $year,
                powerOutputs: $this->activityPowerRepository->findBestForSportTypesInDateRange(
                    sportTypes: SportTypes::thatSupportPeakPowerOutputs(),
                    dateRange: $year->getRange(),
                )
            );
        }

        $this->buildStorage->write(
            'power-output.html',
            $this->twig->load('html/dashboard/power-output.html.twig')->render([
                'powerOutputChart' => Json::encode(
                    PowerOutputChart::create($bestPowerOutputs)->build()
                ),
                'bestPowerOutputs' => $bestPowerOutputs,
            ]),
        );
    }
}
