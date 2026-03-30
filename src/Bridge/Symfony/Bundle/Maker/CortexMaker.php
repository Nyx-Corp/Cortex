<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker;

use Cortex\Bridge\Symfony\Bundle\Maker\Helper\PathCollection;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
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

    /**
     * Resolves the output root directory, path transformer and namespace replacements for --root-path.
     *
     * When --root-path is provided (e.g., src/Lib/Synapse), files are generated there
     * instead of the project root. Directory structure and namespace prefixes are rewritten:
     *   src/Domain/          → src/Component/
     *   src/Infrastructure/Doctrine/ → src/Bridge/Doctrine/
     *   src/Application/     → src/Bridge/Symfony/
     *   templates/           → src/Bridge/Symfony/Bundle/Resources/views/
     *   migrations/          → migrations/ (kept at root)
     *
     * @return array{destPath: string, namespaceReplacements: array<string, string>, pathTransformer: ?\Closure}
     */
    protected function resolveRootPath(InputInterface $input, Generator $generator): array
    {
        $rootPath = $input->getOption('root-path') ?? '';
        if ('' === $rootPath) {
            return ['destPath' => $generator->getRootDirectory(), 'namespaceReplacements' => [], 'pathTransformer' => null];
        }

        $rootNamespace = $input->getOption('root-namespace') ?? '';
        if ('' === $rootNamespace) {
            throw new \InvalidArgumentException('--root-namespace is required when using --root-path');
        }

        // Build absolute path
        $destPath = str_starts_with($rootPath, '/')
            ? $rootPath
            : $generator->getRootDirectory().'/'.$rootPath;

        // Remap directory structure to match lib convention (Cortex-like)
        $pathTransformer = static function (string $path) use ($destPath, $generator): string {
            // migrations/ go to project root, not inside the lib
            if (str_contains($path, '/migrations/')) {
                return str_replace($destPath, $generator->getRootDirectory(), $path);
            }

            $path = str_replace('/src/Domain/', '/src/Component/', $path);
            $path = str_replace('/src/Infrastructure/Doctrine/', '/src/Bridge/Doctrine/', $path);
            $path = str_replace('/src/Application/', '/src/Bridge/Symfony/', $path);
            $path = str_replace('/templates/', '/src/Bridge/Symfony/Bundle/Resources/views/', $path);
            $path = str_replace('/tests/Functional/Application/', '/tests/Functional/Bridge/Symfony/', $path);

            return $path;
        };

        return [
            'destPath' => $destPath,
            'pathTransformer' => $pathTransformer,
            'namespaceReplacements' => [
                'namespace Domain\\' => 'namespace '.$rootNamespace.'\\Component\\',
                'use Domain\\' => 'use '.$rootNamespace.'\\Component\\',
                'namespace Infrastructure\\Doctrine\\' => 'namespace '.$rootNamespace.'\\Bridge\\Doctrine\\',
                'use Infrastructure\\Doctrine\\' => 'use '.$rootNamespace.'\\Bridge\\Doctrine\\',
                'namespace Application\\' => 'namespace '.$rootNamespace.'\\Bridge\\Symfony\\',
                'use Application\\' => 'use '.$rootNamespace.'\\Bridge\\Symfony\\',
            ],
        ];
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
