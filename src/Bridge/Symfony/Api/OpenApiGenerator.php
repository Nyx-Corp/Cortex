<?php

namespace Cortex\Bridge\Symfony\Api;

use Cortex\Bridge\Symfony\Form\CommandFormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OpenApiGenerator
{
    public function __construct(
        private readonly array $actionMetadata,
        private readonly FormFactoryInterface $formFactory,
        private readonly TranslatorInterface $translator,
        private readonly string $apiTitle = 'API',
    ) {
    }

    /**
     * Generates a complete OpenAPI 3.1 specification array from action metadata.
     */
    public function generate(int $version = 1, string $serverUrl = '/api'): array
    {
        $spec = [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->apiTitle,
                'version' => sprintf('%d.0.0', $version),
            ],
            'servers' => [
                ['url' => sprintf('%s/v%d', $serverUrl, $version)],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'paths' => [],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
                'schemas' => [
                    'ValidationError' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => ['type' => 'string'],
                            'violations' => ['type' => 'object'],
                        ],
                    ],
                    'DomainError' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => ['type' => 'string'],
                            'domain' => ['type' => 'string'],
                        ],
                    ],
                    'AuthError' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($this->actionMetadata as $commandClass => $meta) {
            // Skip actions not available in this version
            if ($version < ($meta['apiSince'] ?? 1)) {
                continue;
            }

            $domain = strtolower($meta['domain']);
            $model = strtolower($meta['model']);
            $action = strtolower($meta['action']);

            $method = match ($action) {
                'create' => 'post',
                'update' => 'put',
                'archive' => 'delete',
                default => 'post',
            };

            $path = match ($action) {
                'create' => sprintf('/%s/%s', $domain, $model),
                'update' => sprintf('/%s/%s/{uuid}', $domain, $model),
                'archive' => sprintf('/%s/%s/{uuid}', $domain, $model),
                default => sprintf('/%s/%s/{uuid}/%s', $domain, $model, $action),
            };

            $operation = $this->buildOperation($commandClass, $meta);

            // Mark deprecated operations
            if (($meta['apiDeprecated'] ?? null) !== null && $version >= $meta['apiDeprecated']) {
                $operation['deprecated'] = true;
                if ($meta['apiSunset'] ?? null) {
                    $operation['x-sunset'] = $meta['apiSunset'];
                }
            }

            if (str_contains($path, '{uuid}')) {
                $operation['parameters'] = [
                    [
                        'name' => 'uuid',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string', 'format' => 'uuid'],
                    ],
                ];
            }

            $spec['paths'][$path][$method] = $operation;
        }

        return $spec;
    }

    private function buildOperation(string $commandClass, array $meta): array
    {
        $domain = strtolower($meta['domain']);
        $modelKey = strtolower($meta['model']);
        $operationId = sprintf('%s_%s_%s', $domain, $this->camelToSnake($meta['model']), $this->camelToSnake($meta['action']));

        $descKey = $modelKey.'.form.description';
        $summary = $this->translator->trans($descKey, [], $domain);
        if ($summary === $descKey) {
            $summary = sprintf('%s %s', $meta['action'], $meta['model']);
        }

        $operation = [
            'operationId' => $operationId,
            'summary' => $summary,
            'tags' => [ucfirst($meta['domain'])],
            'responses' => [
                '200' => [
                    'description' => 'Success',
                    'content' => ['application/json' => ['schema' => ['type' => 'object']]],
                ],
                '400' => [
                    'description' => 'Validation failed',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/ValidationError']]],
                ],
                '401' => [
                    'description' => 'Unauthorized',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/AuthError']]],
                ],
                '422' => [
                    'description' => 'Domain error',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DomainError']]],
                ],
            ],
        ];

        $schema = $this->buildRequestSchema($commandClass, $meta);

        if (!empty($schema['properties'])) {
            $operation['requestBody'] = [
                'required' => true,
                'content' => ['application/json' => ['schema' => $schema]],
            ];
        }

        return $operation;
    }

    private function buildRequestSchema(string $commandClass, array $meta): array
    {
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

            $descriptionKey = $child->vars['help'] ?? $child->vars['label'] ?? $fieldName;
            $translationDomain = $child->vars['translation_domain'] ?? null;

            if ($descriptionKey && false !== $translationDomain) {
                $translated = $this->translator->trans($descriptionKey, [], $translationDomain);
                if ($translated !== $descriptionKey) {
                    $prop['description'] = $translated;
                }
            }

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

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
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
}
