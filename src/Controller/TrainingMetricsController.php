<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Domain\Strava\Activity\ActivitiesEnricher;
use App\Domain\Strava\Activity\TrainingLoadChart;
use App\Domain\Strava\Activity\PolarizedTrainingDistributionChart;
use App\Domain\Strava\Athlete\MaxHeartRate\MaxHeartRateFormula;
use App\Domain\Strava\Athlete\Athlete;
use App\Infrastructure\Serialization\Json;

final class TrainingMetricsController extends AbstractController
{
    public function __construct(
        private readonly ActivitiesEnricher $activitiesEnricher,
        private readonly MaxHeartRateFormula $maxHeartRateFormula,
        private readonly Athlete $athlete
    ) {
    }

    #[Route('/training-metrics', name: 'app_training_metrics')]
    public function __invoke(): Response
    {
        // Compute daily TRIMP-based load data
        $activities = $this->activitiesEnricher->getEnrichedActivities();
        $dailyLoad = [];
        foreach ($activities as $act) {
            $day = $act->getStartDate()->format('Y-m-d');
            if (!isset($dailyLoad[$day])) {
                $dailyLoad[$day] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
            }
            $hr = $act->getAverageHeartRate();
            $sec = $act->getMovingTimeInSeconds();
            if ($hr && $sec > 0) {
                $age = $this->athlete->getAgeInYears($act->getStartDate());
                $maxHr = $this->maxHeartRateFormula->calculate($age, $act->getStartDate());
                $intensity = $hr / $maxHr;
                $trimp = ($sec / 60) * $intensity * 1.92 * exp(1.67 * $intensity);
                $dailyLoad[$day]['trimp']     += $trimp;
                $dailyLoad[$day]['duration']  += $sec;
                $dailyLoad[$day]['intensity'] += $sec * $intensity;
            }
        }
        // Fill in missing days
        if (!empty($dailyLoad)) {
            $days = array_keys($dailyLoad);
            sort($days);
            $current = $days[0];
            $last = end($days);
            while ($current < $last) {
                $next = date('Y-m-d', strtotime($current.' +1 day'));
                if (!isset($dailyLoad[$next])) {
                    $dailyLoad[$next] = ['trimp' => 0, 'duration' => 0, 'intensity' => 0];
                }
                $current = $next;
            }
            ksort($dailyLoad);
        }

        // Build Training Load chart options (4 months history + 1 month projection)
        $loadOpts = TrainingLoadChart::fromDailyLoadData($dailyLoad)->build(true);
        $loadJson = Json::encode($loadOpts);
        
        // Approximate Polarized Distribution by assigning each activity's moving time to zones based on average HR
        $zoneData = [];
        foreach ($activities as $act) {
            $hr = $act->getAverageHeartRate();
            $sec = $act->getMovingTimeInSeconds();
            if (! $hr || $sec <= 0) {
                continue;
            }
            $day = $act->getStartDate()->format('Y-m-d');
            $age = $this->athlete->getAgeInYears($act->getStartDate());
            $maxHr = $this->maxHeartRateFormula->calculate($age, $act->getStartDate());
            $intensity = $hr / $maxHr;
            if ($intensity < 0.6) {
                $zone = '1';
            } elseif ($intensity < 0.7) {
                $zone = '2';
            } elseif ($intensity < 0.8) {
                $zone = '3';
            } elseif ($intensity < 0.9) {
                $zone = '4';
            } else {
                $zone = '5';
            }
            $zoneData[$day][$zone] = ($zoneData[$day][$zone] ?? 0) + $sec;
        }
        $polarOpts = PolarizedTrainingDistributionChart::fromTrainingZoneData($zoneData)->build();
        $polarJson = Json::encode($polarOpts);

        return $this->render('html/dashboard/training-metrics.html.twig', [
            'trainingLoadChart'                  => $loadJson,
            'polarizedTrainingDistributionChart' => $polarJson,
            'hrvChart'                           => '{}',
            'relativeEffortChart'                => '{}',
            'vo2MaxTrendsChart'                  => '{}',
            'currentCtl'                         => 0,
            'currentAtl'                         => 0,
            'currentTsb'                         => 0,
            'acRatio'                            => 0,
            'restDaysLastWeek'                   => 0,
            'monotony'                           => 0,
            'strain'                             => 0,
            'weeklyTrimp'                        => 0,
        ]);
    }
}
