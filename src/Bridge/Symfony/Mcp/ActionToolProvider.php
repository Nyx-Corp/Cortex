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

            if (CommandFormType::class === $formType) {
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
                    'properties' => (object) $properties,
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

            if (CommandFormType::class === $formType) {
                $formOptions['command_class'] = $commandClass;
            }

            $form = $this->formFactory->create($formType, null, $formOptions);
            $args = $this->castArgsForForm($form, $args);
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

    /**
     * Cast MCP arguments to match Symfony form expectations.
     *
     * Checkboxes expect "1"/null, integers expect int values.
     */
    private function castArgsForForm(\Symfony\Component\Form\FormInterface $form, array $args): array
    {
        foreach ($form->all() as $fieldName => $child) {
            if (!\array_key_exists($fieldName, $args)) {
                continue;
            }

            $innerType = $child->getConfig()->getType()->getInnerType();

            // CheckboxType: true → "1", false → null (Symfony checkbox convention)
            if ($innerType instanceof \Symfony\Component\Form\Extension\Core\Type\CheckboxType) {
                $val = $args[$fieldName];
                if (true === $val || '1' === $val || 'true' === $val) {
                    $args[$fieldName] = '1';
                } else {
                    unset($args[$fieldName]);
                }
                continue;
            }

            // IntegerType / NumberType: cast to int/float
            if ($innerType instanceof \Symfony\Component\Form\Extension\Core\Type\IntegerType) {
                $args[$fieldName] = (int) $args[$fieldName];
            } elseif ($innerType instanceof \Symfony\Component\Form\Extension\Core\Type\NumberType) {
                $args[$fieldName] = (float) $args[$fieldName];
            }
        }

        return $args;
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
