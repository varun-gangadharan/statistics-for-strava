<?php

declare(strict_types=1);

namespace App\Domain\Strava\Challenge;

enum ConsistencyChallenge: string
{
    case RIDE_KM_200 = 'Ride a total of 200km';
    case RIDE_KM_600 = 'Ride a total of 600km';
    case RIDE_KM_1250 = 'Ride a total of 1250km';
    case RIDE_GRAN_FONDO = 'Complete a 100km ride';
    case RIDE_CLIMBING_7500 = 'Climb a total of 7500m';
    case TWO_DAYS_OF_ACTIVITY_4_WEEKS = '2 days of activity for 4 weeks';
}
