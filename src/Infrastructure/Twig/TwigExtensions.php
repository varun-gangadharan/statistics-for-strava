<?php

namespace App\Infrastructure\Twig;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TwigExtensions extends AbstractExtension
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('repeat', [StrRepeatTwigExtension::class, 'doRepeat']),
            new TwigFilter('ellipses', [StrEllipsisTwigExtension::class, 'doEllipses']),
            new TwigFilter('formatNumber', [FormatNumberTwigExtension::class, 'doFormat']),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('image64', [Base64TwigExtension::class, 'image']),
            new TwigFunction('font64', [Base64TwigExtension::class, 'font']),
            new TwigFunction('render', [new RenderTemplateTwigExtension($this->twig), 'render']),
            new TwigFunction('renderComponent', [new RenderTemplateTwigExtension($this->twig), 'renderComponent']),
            new TwigFunction('renderSvg', [new RenderTemplateTwigExtension($this->twig), 'renderSvg']),
        ];
    }
}
