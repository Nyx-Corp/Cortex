<?php

namespace Cortex\Bridge\Symfony\Twig;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Extension extends AbstractExtension
{
    public function __construct(
        private Environment $twig,
        private string $iconTheme = '@_theme/components/icons.html.twig',
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_block', [$this, 'renderBlock'], ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'icon',
                fn (string $name, array $context = []) => $this->renderBlock(
                    'icon',
                    $this->iconTheme,
                    array_replace_recursive($context, ['name' => $name])
                ),
                ['is_safe' => ['html']]
            ),
            new TwigFilter('unique', 'array_unique'),
            new TwigFilter('pad', 'str_pad'),
        ];
    }

    public function renderBlock(string $block, string $template, array $context = []): string
    {
        $tpl = $this->twig->load($template);

        if (!$tpl->hasBlock($block)) {
            throw new \RuntimeException("Block '$block' not found in template '$template'.");
        }

        return $tpl->renderBlock($block, $context);
    }
}
