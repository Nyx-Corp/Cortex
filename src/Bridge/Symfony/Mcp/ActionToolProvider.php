<?php

namespace Cortex\Bridge\Symfony\Mcp;

use Cortex\Bridge\Symfony\Form\CommandFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ActionToolProvider
{
    public function __construct(
        private readonly array $actionMetadata,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Returns MCP tool definitions for all registered domain actions.
     *
     * Schemas are derived from FormView (types, choices, required) and
     * descriptions from the translation system (help/label keys).
     *
     * @return array<string, array{name: string, description: string, inputSchema: array}>
     */
    public function getTools(): array
    {
        $tools = [];

        foreach ($this->actionMetadata as $commandClass => $meta) {
            $name = sprintf(
                '%s_%s_%s',
                strtolower($meta['domain']),
                $this->camelToSnake($meta['model']),
                $this->camelToSnake($meta['action'])
            );

            $formType = $meta['formType'];
            $formOptions = ['csrf_protection' => false];

            if ($formType === CommandFormType::class) {
                $formOptions['command_class'] = $commandClass;
            }

            $form = $this->formFactory->create($formType, null, $formOptions);
            $view = $form->createView();

            $properties = [];
            $required = [];

            foreach ($view->children as $fieldName => $child) {
                $prop = [
                    'type' => $this->formTypeToJsonSchema($child->vars['block_prefixes'] ?? []),
                ];

                // Description from help text or label (translated)
                $descriptionKey = $child->vars['help'] ?? $child->vars['label'] ?? $fieldName;
                $translationDomain = $child->vars['translation_domain'] ?? null;

                if ($descriptionKey && false !== $translationDomain) {
                    $translated = $this->translator->trans($descriptionKey, [], $translationDomain);
                    if ($translated !== $descriptionKey) {
                        $prop['description'] = $translated;
                    }
                }

                // Enum choices
                if (!empty($child->vars['choices'])) {
                    $prop['enum'] = array_map(
                        fn ($choice) => $choice->value,
                        $child->vars['choices']
                    );
                }

                $properties[$fieldName] = $prop;

                if ($child->vars['required'] ?? false) {
                    $required[] = $fieldName;
                }
            }

            // Tool description from translation: {model}.form.description
            $modelKey = strtolower($meta['model']);
            $domainKey = strtolower($meta['domain']);
            $descKey = $modelKey.'.form.description';
            $description = $this->translator->trans($descKey, [], $domainKey);

            // Fallback to generic description if translation key not found
            if ($description === $descKey) {
                $description = sprintf(
                    '%s %s (%s domain)',
                    $meta['action'],
                    $meta['model'],
                    $meta['domain']
                );
            }

            $tools[$name] = [
                'name' => $name,
                'description' => $description,
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                ],
            ];
        }

        return $tools;
    }

    /**
     * Handles an MCP tool call by submitting data through the form system.
     */
    public function handleToolCall(string $name, array $args): array
    {
        // Find matching action
        foreach ($this->actionMetadata as $commandClass => $meta) {
            $toolName = sprintf(
                '%s_%s_%s',
                strtolower($meta['domain']),
                $this->camelToSnake($meta['model']),
                $this->camelToSnake($meta['action'])
            );

            if ($toolName !== $name) {
                continue;
            }

            $formType = $meta['formType'];
            $formOptions = ['csrf_protection' => false];

            if ($formType === CommandFormType::class) {
                $formOptions['command_class'] = $commandClass;
            }

            $form = $this->formFactory->create($formType, null, $formOptions);
            $form->submit($args);

            if (!$form->isValid()) {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }

                return ['error' => 'Validation failed', 'violations' => $errors];
            }

            return $this->serialize($form->getData());
        }

        return ['error' => sprintf('Unknown tool "%s".', $name)];
    }

    private function formTypeToJsonSchema(array $blockPrefixes): string
    {
        $typeMap = [
            'integer' => 'integer',
            'number' => 'number',
            'checkbox' => 'boolean',
            'date_time' => 'string',
            'date' => 'string',
            'choice' => 'string',
            'textarea' => 'string',
        ];

        // Check from most specific (last) to least specific (first)
        foreach (array_reverse($blockPrefixes) as $prefix) {
            if (isset($typeMap[$prefix])) {
                return $typeMap[$prefix];
            }
        }

        return 'string';
    }

    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    private function serialize(mixed $value): mixed
    {
        if (is_object($value)) {
            if ($value instanceof \BackedEnum) {
                return $value->value;
            }
            if ($value instanceof \DateTimeInterface) {
                return $value->format('c');
            }

            $result = [];
            foreach (get_object_vars($value) as $key => $val) {
                $result[$key] = $this->serialize($val);
            }

            return $result;
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->serialize($v), $value);
        }

        return $value;
    }
}
