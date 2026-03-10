<?php

namespace Cortex\Bridge\Symfony\Bundle\DependencyInjection\Compiler;

use Cortex\Bridge\Symfony\Form\Attribute\Action;
use Cortex\Bridge\Symfony\Form\Attribute\Api;
use Cortex\Bridge\Symfony\Form\CommandFormType;
use Cortex\Bridge\Symfony\Form\CommandMapperExtension;
use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Action\ActionHandlerCollection;
use Cortex\Component\Event\EventDispatcherAwareInterface;
use Cortex\Component\Model\Factory\ModelFactory;
use Cortex\ValueObject\RegisteredClass;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as FormType;

class ActionHandlerCompilerPass implements CompilerPassInterface
{
    private const METADATA_PARAM = 'cortex.action_metadata';

    /** @var array<string, Api> commandClass => Api attribute instance */
    private array $apiVersionInfo = [];

    private const FIELD_TYPE_MAP = [
        'string' => FormType\TextType::class,
        'int' => FormType\IntegerType::class,
        'float' => FormType\NumberType::class,
        'bool' => FormType\CheckboxType::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ActionHandlerCollection::class)) {
            return;
        }

        $actionHandlerCollectionDefinition = $container->getDefinition(ActionHandlerCollection::class);
        $handlerCommandMapping = [];

        // 1. Discover handlers and map commands
        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (!$class || !class_exists($class) || !is_subclass_of($class, ActionHandler::class)) {
                continue;
            }

            $commandClass = new RegisteredClass(
                preg_replace('/Handler$/', 'Command', $class)
            );

            $handlerCommandMapping[$commandClass->value] = new Reference($id);

            if (is_subclass_of($class, EventDispatcherAwareInterface::class)) {
                $definition->addMethodCall(
                    'setEventDispatcher',
                    [new Reference(EventDispatcherInterface::class)]
                );
            }
        }

        $actionHandlerCollectionDefinition->setArgument(0, $handlerCommandMapping);

        // 2. Extract action metadata from command namespaces
        $factoryMapping = $this->getFactoryMapping($container);
        $actionMetadata = $this->buildActionMetadata($handlerCommandMapping, $factoryMapping);

        // 3. Scan #[Action] attributes on FormTypes
        $actionAttributeMapping = $this->scanActionAttributes($container);

        // 4. Build CommandFormType fields config for commands without dedicated FormTypes
        $fieldsConfig = $this->buildFieldsConfig(
            $handlerCommandMapping,
            $actionAttributeMapping,
            $factoryMapping
        );

        // 5. Inject into CommandFormType
        if ($container->hasDefinition(CommandFormType::class)) {
            $container->getDefinition(CommandFormType::class)
                ->setArgument(0, $fieldsConfig)
                ->setArgument(1, $factoryMapping);
        }

        // 6. Inject #[Action] mapping into CommandMapperExtension
        if ($container->hasDefinition(CommandMapperExtension::class)) {
            $container->getDefinition(CommandMapperExtension::class)
                ->setArgument('$actionAttributeMapping', $actionAttributeMapping);
        }

        // 7. Update action metadata with form type and API version info
        foreach ($actionMetadata as $commandClass => &$meta) {
            // Check if command has a dedicated FormType via #[Action] attribute
            $formTypeFromAttribute = array_search($commandClass, $actionAttributeMapping, true);
            if (false !== $formTypeFromAttribute) {
                $meta['formType'] = $formTypeFromAttribute;
            } else {
                $meta['formType'] = CommandFormType::class;
            }

            // API version info from #[Api] attribute on the FormType
            if (isset($this->apiVersionInfo[$commandClass])) {
                $api = $this->apiVersionInfo[$commandClass];
                $meta['apiSince'] = $api->since;
                $meta['apiDeprecated'] = $api->deprecated;
                $meta['apiSunset'] = $api->sunset;
            } else {
                $meta['apiSince'] = 1;
                $meta['apiDeprecated'] = null;
                $meta['apiSunset'] = null;
            }
        }
        unset($meta);

        // 8. Store metadata as container parameter
        $container->setParameter(self::METADATA_PARAM, $actionMetadata);

        // 8b. Derive active API versions from metadata
        $activeVersions = array_values(array_unique(
            array_map(fn (array $m) => $m['apiSince'], $actionMetadata)
        ));
        sort($activeVersions);
        if (empty($activeVersions)) {
            $activeVersions = [1];
        }

        // 9. Inject metadata into ApiRouteLoader and ActionToolProvider
        if ($container->hasDefinition(\Cortex\Bridge\Symfony\Api\ApiRouteLoader::class)) {
            $container->getDefinition(\Cortex\Bridge\Symfony\Api\ApiRouteLoader::class)
                ->setArgument('$actionMetadata', $actionMetadata);
        }

        if ($container->hasDefinition(\Cortex\Bridge\Symfony\Mcp\ActionToolProvider::class)) {
            $container->getDefinition(\Cortex\Bridge\Symfony\Mcp\ActionToolProvider::class)
                ->setArgument('$actionMetadata', $actionMetadata);
        }

        // SecuredActionToolProvider lives in Gandalf (external) — use string to avoid hard dependency
        if ($container->hasDefinition('Gandalf\\Bridge\\Symfony\\Security\\SecuredActionToolProvider')) {
            $container->getDefinition('Gandalf\\Bridge\\Symfony\\Security\\SecuredActionToolProvider')
                ->setArgument('$actionMetadata', $actionMetadata);
        }

        if ($container->hasDefinition(\Cortex\Bridge\Symfony\Api\OpenApiGenerator::class)) {
            $container->getDefinition(\Cortex\Bridge\Symfony\Api\OpenApiGenerator::class)
                ->setArgument('$actionMetadata', $actionMetadata);
        }

        // 9b. Inject active API versions
        foreach ([
            \Cortex\Bridge\Symfony\Api\ApiRouteLoader::class,
            \Cortex\Bridge\Symfony\Api\OpenApiController::class,
            \Cortex\Bridge\Symfony\Console\OpenApiDumpCommand::class,
        ] as $serviceClass) {
            if ($container->hasDefinition($serviceClass)) {
                $container->getDefinition($serviceClass)
                    ->setArgument('$activeVersions', $activeVersions);
            }
        }

        // 10. Register CLI commands
        $this->registerCliCommands($container, $actionMetadata);
    }

    private function getFactoryMapping(ContainerBuilder $container): array
    {
        $mapping = [];

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (!$class || !class_exists($class) || !is_subclass_of($class, ModelFactory::class)) {
                continue;
            }

            $ref = new \ReflectionClass($class);
            $modelAttr = $ref->getAttributes(\Cortex\Component\Model\Attribute\Model::class)[0] ?? null;
            if ($modelAttr) {
                $instance = $modelAttr->newInstance();
                $mapping[(string) $instance->model] = new Reference($id);
            }
        }

        return $mapping;
    }

    /**
     * Extract domain/model/action from command namespace.
     * Convention: Domain\{Domain}\Action\{ModelAction}\Command.
     */
    private function buildActionMetadata(array $handlerCommandMapping, array $factoryMapping): array
    {
        $knownModels = array_keys($factoryMapping);
        $metadata = [];

        foreach (array_keys($handlerCommandMapping) as $commandClass) {
            // Parse namespace: Domain\{Domain}\Action\{ModelAction}\Command
            if (!preg_match('/^Domain\\\\(\w+)\\\\Action\\\\(\w+)\\\\Command$/', $commandClass, $matches)) {
                continue;
            }

            $domain = $matches[1];
            $modelAction = $matches[2];

            // Split ModelAction using known model names
            [$model, $action] = $this->splitModelAction($modelAction, $knownModels, $domain);

            $metadata[$commandClass] = [
                'commandClass' => $commandClass,
                'domain' => $domain,
                'model' => $model,
                'action' => $action,
                'formType' => null, // filled later
            ];
        }

        return $metadata;
    }

    private function splitModelAction(string $modelAction, array $knownModels, string $domain): array
    {
        // Try to find the longest matching model name from known models
        foreach ($knownModels as $modelClass) {
            // Extract short model name from FQCN: Domain\{Domain}\Model\{Model}
            if (preg_match('/\\\\Model\\\\(\w+)$/', $modelClass, $m)) {
                $modelName = $m[1];
                if (str_starts_with($modelAction, $modelName)) {
                    $action = substr($modelAction, strlen($modelName));

                    return [$modelName, $action ?: 'Default'];
                }
            }
        }

        // Fallback: use domain name as hint
        return [$modelAction, 'Default'];
    }

    private function scanActionAttributes(ContainerBuilder $container): array
    {
        $mapping = []; // formTypeClass => commandClass

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();

            if (!$class || !class_exists($class) || !is_subclass_of($class, AbstractType::class)) {
                continue;
            }

            $ref = new \ReflectionClass($class);
            $attrs = $ref->getAttributes(Action::class);

            if (empty($attrs)) {
                continue;
            }

            $action = $attrs[0]->newInstance();
            $mapping[$class] = $action->commandClass;

            // Scan #[Api] attribute on the same FormType
            $apiAttrs = $ref->getAttributes(Api::class);
            if (!empty($apiAttrs)) {
                $this->apiVersionInfo[$action->commandClass] = $apiAttrs[0]->newInstance();
            }
        }

        return $mapping;
    }

    private function buildFieldsConfig(array $handlerCommandMapping, array $actionAttributeMapping, array $factoryMapping): array
    {
        $coveredCommands = array_values($actionAttributeMapping);
        $fieldsConfig = [];

        foreach (array_keys($handlerCommandMapping) as $commandClass) {
            // Skip commands that have a dedicated FormType via #[Action]
            if (in_array($commandClass, $coveredCommands, true)) {
                continue;
            }

            $ref = new \ReflectionClass($commandClass);
            $constructor = $ref->getConstructor();

            if (!$constructor) {
                continue;
            }

            $fields = [];
            foreach ($constructor->getParameters() as $param) {
                $field = $this->resolveFieldConfig($param, $factoryMapping);
                if ($field) {
                    $fields[$param->getName()] = $field;
                }
            }

            if (!empty($fields)) {
                $fieldsConfig[$commandClass] = $fields;
            }
        }

        return $fieldsConfig;
    }

    private function resolveFieldConfig(\ReflectionParameter $param, array $factoryMapping): ?array
    {
        $type = $param->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return null;
        }

        $typeName = $type->getName();

        // Primitive types
        if (isset(self::FIELD_TYPE_MAP[$typeName])) {
            $options = ['required' => !$param->isOptional()];

            if ('bool' === $typeName) {
                $options['false_values'] = ['0', 'false', ''];
            }

            return ['type' => self::FIELD_TYPE_MAP[$typeName], 'options' => $options];
        }

        // BackedEnum
        if (class_exists($typeName) && is_subclass_of($typeName, \BackedEnum::class)) {
            return [
                'type' => FormType\EnumType::class,
                'options' => [
                    'class' => $typeName,
                    'required' => !$param->isOptional(),
                ],
            ];
        }

        // DateTimeInterface
        if (\DateTimeInterface::class === $typeName || \DateTimeImmutable::class === $typeName || \DateTime::class === $typeName) {
            return [
                'type' => FormType\DateTimeType::class,
                'options' => [
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'required' => !$param->isOptional(),
                ],
            ];
        }

        // Model known by factory → TextType + ModelTransformer
        if (isset($factoryMapping[$typeName])) {
            return [
                'type' => FormType\TextType::class,
                'options' => ['required' => !$param->isOptional()],
                'model_class' => $typeName,
            ];
        }

        // Uuid
        if (\Symfony\Component\Uid\Uuid::class === $typeName) {
            return [
                'type' => FormType\TextType::class,
                'options' => ['required' => !$param->isOptional()],
            ];
        }

        // array → TextType with json hint
        if ('array' === $typeName) {
            return [
                'type' => FormType\TextType::class,
                'options' => ['required' => !$param->isOptional()],
            ];
        }

        // Default to TextType for other objects
        if (class_exists($typeName)) {
            return [
                'type' => FormType\TextType::class,
                'options' => ['required' => !$param->isOptional()],
            ];
        }

        return null;
    }

    private function registerCliCommands(ContainerBuilder $container, array $actionMetadata): void
    {
        if (!class_exists(\Cortex\Bridge\Symfony\Console\DomainActionCommand::class)) {
            return;
        }

        foreach ($actionMetadata as $commandClass => $meta) {
            $cliName = sprintf(
                '%s:%s:%s',
                strtolower($meta['domain']),
                $this->camelToKebab($meta['model']),
                $this->camelToKebab($meta['action'])
            );

            $definition = new Definition(\Cortex\Bridge\Symfony\Console\DomainActionCommand::class);
            $definition->setArgument('$commandClass', $commandClass);
            $definition->setArgument('$formType', $meta['formType']);
            $definition->setArgument('$cliName', $cliName);
            $definition->setArgument('$meta', $meta);
            $definition->setAutowired(true);
            $definition->addTag('console.command', ['command' => $cliName]);

            $container->setDefinition(
                'cortex.cli.'.$cliName,
                $definition
            );
        }
    }

    private function camelToKebab(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $input));
    }
}
