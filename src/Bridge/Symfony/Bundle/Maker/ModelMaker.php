<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\SplFileInfo;

use function Symfony\Component\String\u;

final class ModelMaker extends CortexMaker
{
    public static function getCommandName(): string
    {
        return 'make:cortex:model';
    }

    public static function getCommandDescription(): string
    {
        return 'Crée un modèle Cortex avec sa structure DDD';
    }

    public static function getGeneratedPaths(bool $withDbal): array
    {
        $paths = [
            'src/Domain/{Domain}/Model/{Model}.php',
            'src/Domain/{Domain}/Model/{Model}Collection.php',
            'src/Domain/{Domain}/Error/{Domain}Exception.php',
            'src/Domain/{Domain}/Error/{Model}Exception.php',
            'src/Domain/{Domain}/Factory/{Model}Factory.php',
            'src/Domain/{Domain}/Persistence/{Model}Store.php',
        ];

        if ($withDbal) {
            array_push(
                $paths,
                'src/Infrastructure/Doctrine/{Domain}/Dbal{Model}Mapper.php',
                'migrations/Version{datetime}.php'
            );
        }

        return $paths;
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->addArgument('domain', InputArgument::REQUIRED, 'Domaine du modèle')
            ->addArgument('model', InputArgument::REQUIRED, 'Nom du modèle')
            ->addOption('dbal', null, InputOption::VALUE_NONE, 'Generate a dbal structure')
        ;
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $domainUnicode = u($input->getArgument('domain'));
        $modelUnicode = u($input->getArgument('model'));

        $this->pathCollection
            ->filter(fn (SplFileInfo $file) => in_array(
                $file->getRelativePathname(),
                self::getGeneratedPaths($input->getOption('dbal'))
            ))
            ->mirror(
                $generator->getRootDirectory(),
                [
                    '{model}' => $model = $modelUnicode->snake()->toString(),
                    '{Model}' => $Model = $modelUnicode->camel()->title()->toString(),
                    '{domain}' => $domain = $domainUnicode->snake()->toString(),
                    '{Domain}' => $Domain = $domainUnicode->camel()->title()->toString(),
                    '{table}' => sprintf('%s_%s', $domain, $model),
                ],
                ['{datetime}' => [date('YmdHis')]],
            )
            ->generate(fn (string $generatedFilepath) => $io->text(sprintf(
                '  ✓ <info>created</info> <comment>%s</comment>',
                $generatedFilepath
            )))
        ;

        $io->success("[Cortex] Model '{$Domain}'/'{$Model}' créé avec succès.");
    }
}
