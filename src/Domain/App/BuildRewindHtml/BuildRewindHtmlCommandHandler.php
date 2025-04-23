<?php

declare(strict_types=1);

namespace App\Domain\App\BuildRewindHtml;

use App\Domain\Strava\Gear\GearRepository;
use App\Domain\Strava\Rewind\FindAvailableRewindYears\FindAvailableRewindYears;
use App\Domain\Strava\Rewind\FindMovingTimePerDay\FindMovingTimePerDay;
use App\Domain\Strava\Rewind\Items\DailyActivitiesChart;
use App\Domain\Strava\Rewind\Items\GearUsageChart;
use App\Domain\Strava\Rewind\Items\PersonalRecordsPerMonthChart;
use App\Domain\Strava\Rewind\RewindItem;
use App\Domain\Strava\Rewind\RewindRepository;
use App\Infrastructure\CQRS\Command\Command;
use App\Infrastructure\CQRS\Command\CommandHandler;
use App\Infrastructure\CQRS\Query\Bus\QueryBus;
use App\Infrastructure\Serialization\Json;
use League\Flysystem\FilesystemOperator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final readonly class BuildRewindHtmlCommandHandler implements CommandHandler
{
    public function __construct(
        private RewindRepository $rewindRepository,
        private GearRepository $gearRepository,
        private QueryBus $queryBus,
        private Environment $twig,
        private FilesystemOperator $buildStorage,
        private TranslatorInterface $translator,
    ) {
    }

    public function handle(Command $command): void
    {
        assert($command instanceof BuildRewindHtml);

        $now = $command->getCurrentDateTime();
        $response = $this->queryBus->ask(new FindAvailableRewindYears($now));
        $availableRewindYears = $response->getAvailableRewindYears();

        $gears = $this->gearRepository->findAll();

        foreach ($availableRewindYears as $availableRewindYear) {
            $longestActivity = $this->rewindRepository->findLongestActivity($availableRewindYear);
            $leafletMap = $longestActivity->getLeafletMap();

            $render = [
                'now' => $now,
                'availableRewindYears' => $availableRewindYears,
                'activeRewindYear' => $availableRewindYear,
                'rewindItems' => [
                    RewindItem::from(
                        icon: 'calendar',
                        title: $this->translator->trans('Daily activities'),
                        subTitle: $this->translator->trans('{numberOfActivities} activities in {year}', [
                            '{numberOfActivities}' => $this->rewindRepository->countActivities($availableRewindYear),
                            '{year}' => $availableRewindYear,
                        ]),
                        content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                            'chart' => Json::encode(DailyActivitiesChart::create(
                                movingTimePerDay: $this->queryBus->ask(new FindMovingTimePerDay($availableRewindYear))->getMovingTimePerDay(),
                                year: $availableRewindYear,
                                translator: $this->translator,
                            )->build()),
                        ]),
                    ),
                    RewindItem::from(
                        icon: 'tools',
                        title: $this->translator->trans('Gear'),
                        subTitle: $this->translator->trans('Total hours spent per gear'),
                        content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                            'chart' => Json::encode(GearUsageChart::create(
                                movingTimePerGear: $this->rewindRepository->findMovingTimePerGear($availableRewindYear),
                                gears: $gears,
                            )->build()),
                        ]),
                    ),
                    RewindItem::from(
                        icon: 'trophy',
                        title: $this->translator->trans('Longest activity (h)'),
                        subTitle: $longestActivity->getName(),
                        content: $this->twig->render('html/rewind/rewind-biggest-activity.html.twig', [
                            'activity' => $longestActivity,
                            'leaflet' => $leafletMap ? [
                                'routes' => [$longestActivity->getPolyline()],
                                'map' => $leafletMap,
                            ] : null,
                        ])
                    ),
                    RewindItem::from(
                        icon: 'medal',
                        title: $this->translator->trans('PRs'),
                        subTitle: $this->translator->trans('PRs achieved per month'),
                        content: $this->twig->render('html/rewind/rewind-chart.html.twig', [
                            'chart' => Json::encode(PersonalRecordsPerMonthChart::create(
                                personalRecordsPerMonth: $this->rewindRepository->findPersonalRecordsPerMonth($availableRewindYear),
                                year: $availableRewindYear,
                                translator: $this->translator,
                            )->build()),
                        ]),
                    ),
                    RewindItem::from(
                        icon: 'thumbs-up',
                        title: $this->translator->trans('Socials'),
                        subTitle: $this->translator->trans('Total kudos and comments received'),
                        content: ''
                    ),
                    RewindItem::from(
                        icon: 'rocket',
                        title: $this->translator->trans('Distance'),
                        subTitle: $this->translator->trans('Total distance per month'),
                        content: ''
                    ),
                    RewindItem::from(
                        icon: 'mountain',
                        title: $this->translator->trans('Elevation'),
                        subTitle: $this->translator->trans('Total elevation per month'),
                        content: ''
                    ),
                    RewindItem::from(
                        icon: 'muscle',
                        title: $this->translator->trans('Activity count'),
                        subTitle: $this->translator->trans('Number of activities per month'),
                        content: ''
                    ),
                    RewindItem::from(
                        icon: 'watch',
                        title: $this->translator->trans('Total hours'),
                        subTitle: $this->translator->trans('Total hours spent per sport type'),
                        content: ''
                    ),
                    RewindItem::from(
                        icon: 'fire',
                        title: $this->translator->trans('Streaks'),
                        subTitle: $this->translator->trans('Longest streaks'),
                        content: ''
                    ),
                    RewindItem::from(
                        icon: 'bed',
                        title: $this->translator->trans('Rest days'),
                        subTitle: $this->translator->trans('Rest days vs. active days'),
                        content: ''
                    ),
                    RewindItem::from(
                        icon: 'clock',
                        title: $this->translator->trans('Start times'),
                        subTitle: $this->translator->trans('Activity start times'),
                        content: ''
                    ),
                    RewindItem::from(
                        icon: 'image',
                        title: $this->translator->trans('Photo'),
                        subTitle: 'TODO: date of picture',
                        content: ''
                    ),
                    RewindItem::from(
                        icon: 'globe',
                        title: $this->translator->trans('Activity locations'),
                        subTitle: $this->translator->trans('Locations over the globe'),
                        content: ''
                    ),
                ],
            ];

            if ($availableRewindYears->getFirst() == $availableRewindYear) {
                $this->buildStorage->write(
                    'rewind.html',
                    $this->twig->load('html/rewind/rewind.html.twig')->render($render),
                );
            }

            $this->buildStorage->write(
                sprintf('rewind/%s.html', $availableRewindYear),
                $this->twig->load('html/rewind/rewind.html.twig')->render($render),
            );
        }
    }
}
