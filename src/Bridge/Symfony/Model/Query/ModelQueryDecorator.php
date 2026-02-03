<?php

namespace Cortex\Bridge\Symfony\Model\Query;

use Cortex\Bridge\Symfony\Form\ModelQueryType;
use Cortex\Component\Model\Query\ModelQuery;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\Request;

class ModelQueryDecorator extends ModelQuery
{
    private ?FormFactoryInterface $formFactory = null;
    private ?Request $request = null;

    private ?FormBuilderInterface $formBuilder = null;
    private ?FormInterface $form = null;

    private array $filtersConfig = [];
    private array $activeFilters = [];

    public function build(FormFactoryInterface $formFactory, Request $request): self
    {
        $this->formFactory = $formFactory;
        $this->request = $request;

        return $this;
    }

    /**
     * Configure the query decorator with sortable fields and filters.
     *
     * Filters can be:
     * - A closure (legacy): fn(FormBuilderInterface $fb) => $fb->add(...)
     * - An array (new): ['field' => ['type' => 'text', 'label' => 'Label'], ...]
     *
     * Filter types: text, enum, boolean, date
     * For enum: add 'choices' => [['value' => 'x', 'label' => 'X'], ...]
     */
    public function decorate(array $sortables, \Closure|array|null $filters = null): self
    {
        if (!$this->request) {
            throw new \BadMethodCallException('ModelQuery cannot be decorated without building it first, using build() method.');
        }

        // Store filters config if array
        if (is_array($filters)) {
            $this->filtersConfig = $filters;
        }

        $this->formBuilder = $this->formFactory->createNamedBuilder(
            '',
            ModelQueryType::class,
            $this,
            [
                'sortable_fields' => $sortables,
                'filters_config' => is_array($filters) ? $filters : [],
            ]
        );

        // Legacy closure support
        if ($filters instanceof \Closure) {
            $filters($this->formBuilder);
        }

        return $this;
    }

    public function resolve(): \Generator
    {
        $qParam = $this->request?->query->get('q', '');

        // Route attributes filters & tags
        if ($this->request) {
            foreach ($this->request->attributes->all() as $key => $value) {
                if (str_starts_with($key, '_')) {   // ignore internal attributes
                    continue;
                }
                if ($this->filters->hasDeclaredKey($key)) {    // filter
                    $this->filterBy($key, $value);
                    continue;
                }

                $this->tags->set($key, $value); // tag
            }

            // Parse q parameter BEFORE form creation so filters are set
            $this->parseQueryString($qParam);
        }

        // Posted filter form
        if ($this->formBuilder) {
            $this->form = $this->formBuilder->getForm();

            // Only handle form submission if there's no q parameter
            // When q is present, filters come from parseQueryString and form is just for display
            if (empty($qParam)) {
                $this->form->handleRequest($this->request);
                if ($this->form->isSubmitted() && !$this->form->isValid()) {
                    throw new BadRequestException(implode(' ; ', array_map(fn (FormError $error) => sprintf('%s : %s', $error->getOrigin()->getName(), $error->getMessage()), iterator_to_array($this->form->getErrors(true, true)))));
                }
            }
        }

        return parent::resolve();
    }

    /**
     * Parse Gmail-style query string: "field:value field2:value2".
     */
    private function parseQueryString(string $query): void
    {
        if (empty($query)) {
            return;
        }

        // Match field:value or field:"value with spaces"
        $pattern = '/(\w+):(?:"([^"]+)"|(\S+))/';
        preg_match_all($pattern, $query, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $field = $match[1];
            $value = $match[2] ?: $match[3];

            // Check if this is a valid filter field
            if ($this->filters->hasDeclaredKey($field) || isset($this->filtersConfig[$field])) {
                $this->filterBy($field, $value);

                // Build active filter for display
                $label = $this->filtersConfig[$field]['label'] ?? ucfirst($field);
                $this->activeFilters[] = [
                    'field' => $field,
                    'label' => $label,
                    'value' => $value,
                    'display' => $this->formatFilterDisplay($field, $value),
                ];
            }
        }
    }

    /**
     * Format filter value for display in chips.
     */
    private function formatFilterDisplay(string $field, string $value): string
    {
        $config = $this->filtersConfig[$field] ?? null;

        if (!$config) {
            return $value;
        }

        $type = $config['type'] ?? '';

        // For enum/choice, find the label
        if ('enum' === $type && isset($config['choices'])) {
            foreach ($config['choices'] as $choice) {
                if (is_array($choice) && ($choice['value'] ?? '') === $value) {
                    return $choice['label'] ?? $value;
                }
            }
        }

        // For checkboxes (comma-separated values), find labels for each value
        if ('checkboxes' === $type && isset($config['choices'])) {
            $values = explode(',', $value);
            $labels = [];
            foreach ($values as $v) {
                $v = trim($v);
                foreach ($config['choices'] as $choice) {
                    if (is_array($choice) && ($choice['value'] ?? '') === $v) {
                        $labels[] = $choice['label'] ?? $v;
                        break;
                    }
                }
            }

            return $labels ? implode(', ', $labels) : $value;
        }

        // For boolean
        if ('boolean' === $type) {
            return 'true' === $value || '1' === $value ? 'Oui' : 'Non';
        }

        return $value;
    }

    public function getDecorator(): FormView
    {
        if (!$this->form) {
            throw new \BadMethodCallException('No decorator defined, query has to be resolved first.');
        }

        $view = $this->form->createView();

        // Add filters config and active filters to view vars
        $view->vars['filters_config'] = $this->filtersConfig;
        $view->vars['active_filters'] = $this->activeFilters;

        return $view;
    }
}
