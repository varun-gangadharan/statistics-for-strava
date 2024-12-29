<?php

declare(strict_types=1);

namespace App\Domain\Strava\Challenge;

use App\Domain\Measurement\Length\Kilometer;
use App\Domain\Measurement\Length\Meter;
use App\Domain\Strava\Activity\Activities;
use App\Domain\Strava\Activity\Activity;
use App\Domain\Strava\Activity\ActivityType;
use App\Domain\Strava\Calendar\Months;
use App\Domain\Strava\Calendar\Week;

final readonly class ChallengeConsistency
{
    private Months $months;

    private function __construct(
        Months $months,
        private Activities $activities,
    ) {
        $this->months = $months->reverse();
    }

    public static function create(
        Months $months,
        Activities $activities,
    ): self {
        return new self(
            months: $months,
            activities: $activities
        );
    }

    public function getMonths(): Months
    {
        return $this->months;
    }

    /**
     * @return array<mixed>
     */
    public function getConsistencies(): array
    {
        $consistency = [];

        /** @var \App\Domain\Strava\Calendar\Month $month */
        foreach ($this->months as $month) {
            $activities = $this->activities->filterOnMonth($month);
            if ($activities->isEmpty()) {
                foreach (ConsistencyChallenge::cases() as $consistencyChallenge) {
                    $consistency[$consistencyChallenge->value][] = 0;
                }
                continue;
            }

            $rideActivities = $activities
                ->filterOnActivityType(ActivityType::RIDE)
                ->mergeWith($activities->filterOnActivityType(ActivityType::VIRTUAL_RIDE));
            $rideTotalDistance = Kilometer::from($rideActivities->sum(fn (Activity $activity) => $activity->getDistance()->toFloat()));
            $rideTotalElevation = Meter::from($rideActivities->sum(fn (Activity $activity) => $activity->getElevation()->toFloat()));

            $consistency[ConsistencyChallenge::RIDE_KM_200->value][] = $rideTotalDistance->toFloat() >= 200;
            $consistency[ConsistencyChallenge::RIDE_KM_600->value][] = $rideTotalDistance->toFloat() >= 600;
            $consistency[ConsistencyChallenge::RIDE_KM_1250->value][] = $rideTotalDistance->toFloat() >= 1250;
            $consistency[ConsistencyChallenge::RIDE_CLIMBING_7500->value][] = $rideTotalElevation->toFloat() >= 7500;
            $consistency[ConsistencyChallenge::RIDE_GRAN_FONDO->value][] = $rideActivities->max(
                fn (Activity $activity) => $activity->getDistance()->toFloat(),
            ) >= 100;

            // First monday of the month until 4 weeks later, sunday.
            $firstMonday = $month->getFirstMonday();
            $week = Week::fromYearAndWeekNumber(
                year: $firstMonday->getYear(),
                weekNumber: $firstMonday->getWeekNumber()
            );
            $hasTwoDaysOfActivity = true;
            for ($i = 0; $i < 4; ++$i) {
                $numberOfActivities = count($this->activities->filterOnWeek($week));
                if ($numberOfActivities < 2) {
                    $hasTwoDaysOfActivity = false;
                    break;
                }
                $week = $week->getNextWeek();
            }

            $consistency[ConsistencyChallenge::TWO_DAYS_OF_ACTIVITY_4_WEEKS->value][] = $hasTwoDaysOfActivity;
        }

        // Filter out challenges that have never been completed.
        foreach ($consistency as $challenge => $achievements) {
            if (!empty(array_filter($achievements))) {
                continue;
            }
            unset($consistency[$challenge]);
        }

        return $consistency;
    }
}
