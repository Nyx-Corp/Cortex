<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker;

use Cortex\Bridge\Symfony\Bundle\Maker\Helper\PathCollection;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\HttpKernel\Config\FileLocator;

abstract class CortexMaker extends AbstractMaker
{
    protected PathCollection $pathCollection;

    public function __construct(
        string $templateFolder,
        FileLocator $fileLocator,
    ) {
        $this->pathCollection = PathCollection::scan(
            $fileLocator->locate($templateFolder)
        );
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // aucune dépendance supplémentaire requise
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        // pas d'interaction pour ce maker simple
    }
}
