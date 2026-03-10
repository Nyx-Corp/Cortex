<?php

namespace Cortex\Bridge\Symfony\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Form\FormFactoryInterface;

class DomainActionCommand extends Command
{
    public function __construct(
        private readonly string $commandClass,
        private readonly string $formType,
        private readonly string $cliName,
        private readonly array $meta,
        private readonly FormFactoryInterface $formFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName($this->cliName);
        $this->setDescription(sprintf(
            '%s %s %s',
            $this->meta['action'],
            $this->meta['model'],
            $this->meta['domain']
        ));

        $this->addOption('format', null, InputOption::VALUE_OPTIONAL, 'Output format (table or json)', 'table');

        $ref = new \ReflectionClass($this->commandClass);
        $constructor = $ref->getConstructor();

        if (!$constructor) {
            return;
        }

        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

            if ($param->isOptional()) {
                $default = $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null;

                $this->addOption(
                    $param->getName(),
                    null,
                    InputOption::VALUE_OPTIONAL,
                    sprintf('%s (%s)', $param->getName(), $typeName),
                    is_scalar($default) || null === $default ? $default : null,
                );
            } else {
                $this->addOption(
                    $param->getName(),
                    null,
                    InputOption::VALUE_REQUIRED,
                    sprintf('%s (%s)', $param->getName(), $typeName),
                );
            }
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Collect data from options
        $data = [];
        $ref = new \ReflectionClass($this->commandClass);
        $constructor = $ref->getConstructor();

        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                $value = $input->getOption($param->getName());
                if (null !== $value) {
                    $data[$param->getName()] = $value;
                }
            }
        }

        // Submit through form system
        $formOptions = ['csrf_protection' => false];
        if (\Cortex\Bridge\Symfony\Form\CommandFormType::class === $this->formType) {
            $formOptions['command_class'] = $this->commandClass;
        }

        $form = $this->formFactory->create($this->formType, null, $formOptions);

        $form->submit($data);

        if (!$form->isValid()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            $io->error('Validation failed:');
            $io->listing($errors);

            return Command::FAILURE;
        }

        $result = $form->getData();
        $format = $input->getOption('format');

        if ('json' === $format) {
            $output->writeln(json_encode(
                $this->serializeResult($result),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));
        } else {
            $io->success(sprintf(
                '%s:%s:%s executed successfully.',
                strtolower($this->meta['domain']),
                strtolower($this->meta['model']),
                strtolower($this->meta['action'])
            ));

            $serialized = $this->serializeResult($result);
            if (is_array($serialized)) {
                $rows = [];
                foreach ($serialized as $key => $value) {
                    $rows[] = [$key, is_scalar($value) ? (string) $value : json_encode($value)];
                }
                $io->table(['Property', 'Value'], $rows);
            }
        }

        return Command::SUCCESS;
    }

    private function serializeResult(mixed $result): mixed
    {
        if (is_object($result)) {
            $vars = get_object_vars($result);
            $serialized = [];

            foreach ($vars as $key => $value) {
                $serialized[$key] = $this->serializeResult($value);
            }

            return $serialized;
        }

        if (is_array($result)) {
            return array_map(fn ($v) => $this->serializeResult($v), $result);
        }

        if ($result instanceof \BackedEnum) {
            return $result->value;
        }

        if ($result instanceof \DateTimeInterface) {
            return $result->format('c');
        }

        if ($result instanceof \Stringable) {
            return (string) $result;
        }

        return $result;
    }
}
