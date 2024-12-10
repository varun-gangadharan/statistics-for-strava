<?php

namespace App\Domain\Strava\Challenge;

interface ChallengeRepository
{
    public function add(Challenge $challenge): void;

    public function findAll(): Challenges;

    public function find(ChallengeId $challengeId): Challenge;
}
