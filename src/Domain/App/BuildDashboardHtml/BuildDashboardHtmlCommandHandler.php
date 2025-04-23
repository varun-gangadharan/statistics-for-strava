<?php

declare(strict_types=1);

namespace App\Domain\App\BuildDashboardHtml;

use App\Domain\Strava\Activity\ActivitiesEnricher;
use App\Domain\Strava\Activity\ActivityHeatmapChart;
use App\Domain\Strava\Activity\ActivityIntensity;
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
use App\Domain\Strava\Activity\WeekdayStats\WeekdayStats;
use App\Domain\Strava\Activity\WeekdayStats\WeekdayStatsChart;
use App\Domain\Strava\Activity\TrainingLoadChart;
use App\Domain\Strava\Activity\WeeklyDistanceTimeChart;
use App\Domain\Strava\Activity\YearlyDistance\YearlyDistanceChart;
use App\Domain\Strava\Activity\YearlyDistance\YearlyStatistics;
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
use App\Domain\Strava\Activity\HrvChart;
use App\Domain\Strava\Activity\PolarizedTrainingDistributionChart;
use App\Domain\Strava\Activity\RelativeEffortChart;
use App\Domain\Strava\Activity\Vo2MaxTrendsChart;
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
        private \App\Domain\Strava\Athlete\MaxHeartRate\MaxHeartRateFormula $maxHeartRateFormula,
        private \App\Domain\Strava\Athlete\Athlete $athlete,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildDashboardHtml);

        $now = $command->getCurrentDateTime();
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
        
        // Generate training metrics page with CTL, ATL, TSB, etc.
        // Initialize variables to ensure they exist even if calculation fails
        $currentCtl = 0;
        $currentAtl = 0;
        $currentTsb = 0;
        $acRatio = 0;
        $weeklyTrimp = 0;
        $restDaysLastWeek = 0;
        $monotony = 0;
        $strain = 0;
        
        $allActivitiesByDate = [];
        $dailyLoadData = [];
        
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
                $athleteAge = $this->athlete->getAgeInYears($activityDate);
                $maxHr = $this->maxHeartRateFormula->calculate($athleteAge, $activityDate);
                
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
                    if ($dailyLoadData[$date]['trimp'] == 0) {
                        $restDaysLastWeek++;
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
            'dashboard.html',
            $this->twig->load('html/dashboard/dashboard.html.twig')->render([
                'timeIntervals' => ActivityPowerRepository::TIME_INTERVALS_IN_SECONDS_REDACTED,
                'mostRecentActivities' => $allActivities->slice(0, 5),
                'intro' => $activityTotals,
                'weeklyDistanceCharts' => $weeklyDistanceTimeCharts,
                'powerOutputs' => $bestAllTimePowerOutputs,
                'activityHeatmapChart' => Json::encode(
                    ActivityHeatmapChart::create(
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
                // Training load metrics for the dashboard
                'currentCtl' => round($currentCtl, 1),
                'currentAtl' => round($currentAtl, 1),
                'currentTsb' => round($currentTsb, 1),
                'restDaysLastWeek' => $restDaysLastWeek,
                'acRatio' => round($acRatio, 2),
                'monotony' => round($monotony, 2),
                'strain' => round($strain, 0),
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
        
        $this->buildStorage->write(
            'training-metrics.html',
            $this->twig->load('html/dashboard/training-metrics.html.twig')->render([
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
                // Additional training metrics charts
                'hrvChart' => Json::encode(HrvChart::fromHrvData([], [])->build()),
                'polarizedTrainingDistributionChart' => Json::encode(PolarizedTrainingDistributionChart::fromTrainingZoneData([])->build()),
                'relativeEffortChart' => Json::encode(RelativeEffortChart::fromRelativeEffortData([])->build()),
                'vo2MaxTrendsChart' => Json::encode(Vo2MaxTrendsChart::fromVo2MaxData([])->build()),
            ]),
        );
    }
}
