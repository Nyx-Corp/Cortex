<?php

namespace Cortex\Bridge\Symfony\Model\Query;

use Cortex\Bridge\Symfony\Form\ModelQueryType;
use Cortex\Component\Model\Query\ModelQuery;
use Cortex\Component\Model\Query\Pager;
use Cortex\Component\Model\Query\SortDirection;
use Cortex\Component\Model\Query\Sorter;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
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
    private bool $archivable = false;

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
     *
     * Set archivable to true for models using the Archivable trait:
     * automatically filters out archived records and adds a toggle in the filter popover.
     */
    public function decorate(array $sortables, \Closure|array|null $filters = null, array $fields = [], bool $archivable = false): self
    {
        if (!$this->request) {
            throw new \BadMethodCallException('ModelQuery cannot be decorated without building it first, using build() method.');
        }

        $this->archivable = $archivable;

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
                'display_fields' => $fields,
            ]
        );

        // Legacy closure support
        if ($filters instanceof \Closure) {
            $filters($this->formBuilder);
        }

        // Archivable: add toggle checkbox to filter form
        if ($archivable) {
            $this->formBuilder->add('archived', CheckboxType::class, [
                'required' => false,
                'mapped' => false,
                'label' => 'admin.list.show_archived',
                'translation_domain' => 'admin',
            ]);
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

            if (empty($qParam)) {
                // No q parameter: handle full form submission (filters + sort + pager)
                $this->form->handleRequest($this->request);
                if ($this->form->isSubmitted() && !$this->form->isValid()) {
                    throw new BadRequestException(implode(' ; ', array_map(fn (FormError $error) => sprintf('%s : %s', $error->getOrigin()->getName(), $error->getMessage()), iterator_to_array($this->form->getErrors(true, true)))));
                }
            } else {
                // q parameter present: filters come from parseQueryString,
                // but sort and pager still need to be read from request
                $this->applySortAndPagerFromRequest();
            }
        }

        // Archivable: toggle between active and archived records
        if ($this->archivable) {
            $this->isShowingArchived()
                ? $this->filterNotNull('archivedAt')
                : $this->filterNull('archivedAt');
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

            if ($this->filters->hasDeclaredKey($field) || isset($this->filtersConfig[$field])) {
                $this->filterBy($field, $value);
            }
        }
    }

    /**
     * Check if the archived toggle is active (from form submission or q parameter).
     */
    private function isShowingArchived(): bool
    {
        // From form submission: checkbox sends "1" when checked
        if ($this->form?->has('archived') && $this->form->get('archived')->getData()) {
            return true;
        }

        // From request param (when form is not submitted, e.g. direct URL)
        if ('1' === $this->request?->query->get('archived')) {
            return true;
        }

        // From q parameter: "archived:yes", "archived:1", "archived:true"
        $qParam = $this->request?->query->get('q', '');
        if ($qParam && preg_match('/archived:(\S+)/i', $qParam, $matches)) {
            return in_array(strtolower($matches[1]), ['1', 'yes', 'true', 'oui'], true);
        }

        return false;
    }

    /**
     * Apply sort and pager from request query params (used when q parameter bypasses form handling).
     */
    private function applySortAndPagerFromRequest(): void
    {
        $query = $this->request->query;

        // Pager
        $page = (int) $query->get('page', 1);
        $limit = (int) $query->get('limit', 20);
        $this->paginate(new Pager($page, $limit));

        // Sort
        $sortString = $query->get('sort', '');
        if ($sortString && preg_match('/^(.+)_(asc|desc)$/i', $sortString, $matches)) {
            $this->sort(new Sorter(
                $matches[1],
                SortDirection::fromString(strtolower($matches[2]))
            ));
        }
    }

    public function getDecorator(): FormView
    {
        if (!$this->form) {
            throw new \BadMethodCallException('No decorator defined, query has to be resolved first.');
        }

        $view = $this->form->createView();

        $view->vars['filters_config'] = $this->filtersConfig;
        $view->vars['pager'] = $this->pager;
        $view->vars['archivable'] = $this->archivable;
        $view->vars['showing_archived'] = $this->archivable && $this->isShowingArchived();

        return $view;
    }
}
