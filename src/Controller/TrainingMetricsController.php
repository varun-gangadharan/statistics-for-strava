<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TrainingMetricsController extends AbstractController
{
    #[Route('/training-metrics', name: 'app_training_metrics')]
    public function __invoke(): Response
    {
        return $this->render('html/dashboard/training-metrics.html.twig', [
            'trainingLoadChart' => '{}',
            'currentCtl' => 'LOL',
            'currentAtl' => 'LOL',
            'currentTsb' => 0,
            'acRatio' => 1.0,
            'restDaysLastWeek' => 'LOL',
            'monotony' => 1.0,
            'strain' => 'LOL',
            'weeklyTrimp' => 'LOL',
            'hrvChart' => null,
            'polarizedTrainingDistributionChart' => null,
            'relativeEffortChart' => null,
            'vo2MaxTrendsChart' => null
        ]);
    }
}
