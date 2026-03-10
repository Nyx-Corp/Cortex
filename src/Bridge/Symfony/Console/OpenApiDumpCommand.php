<?php

namespace Cortex\Bridge\Symfony\Console;

use Cortex\Bridge\Symfony\Api\OpenApiGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'cortex:api:dump',
    description: 'Dump OpenAPI specification to YAML files',
)]
class OpenApiDumpCommand extends Command
{
    public function __construct(
        private readonly OpenApiGenerator $generator,
        private readonly string $projectDir,
        private readonly array $activeVersions = [1],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('output', 'o', InputOption::VALUE_OPTIONAL, 'Output directory', 'public');
        $this->addOption('server-url', null, InputOption::VALUE_OPTIONAL, 'API server URL', '/api');
        $this->addOption('api-version', null, InputOption::VALUE_OPTIONAL, 'Specific API version to dump');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $outputDir = $input->getOption('output');
        $serverUrl = $input->getOption('server-url');
        $targetVersion = $input->getOption('api-version');

        if (!str_starts_with($outputDir, '/')) {
            $outputDir = $this->projectDir.'/'.$outputDir;
        }

        $versions = null !== $targetVersion
            ? [(int) $targetVersion]
            : $this->activeVersions;

        foreach ($versions as $version) {
            $spec = $this->generator->generate($version, $serverUrl);
            $yaml = Yaml::dump($spec, 10, 2);

            $filename = sprintf('openapi-v%d.yaml', $version);
            $outputPath = $outputDir.'/'.$filename;

            file_put_contents($outputPath, $yaml);
            $io->success(sprintf('OpenAPI v%d spec written to %s', $version, $outputPath));
        }

        return Command::SUCCESS;
    }
}
