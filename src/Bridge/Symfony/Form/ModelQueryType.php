<?php

namespace Cortex\Bridge\Symfony\Form;

use Cortex\Bridge\Symfony\Model\Query\ModelQueryDecorator;
use Cortex\Component\Model\Query\Pager;
use Cortex\Component\Model\Query\SortDirection;
use Cortex\Component\Model\Query\Sorter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ModelQueryType extends AbstractType implements DataMapperInterface
{
    public function getBlockPrefix(): string
    {
        return '';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'method' => 'GET',
            'data_class' => ModelQueryDecorator::class,
            'csrf_protection' => false,
            'page_sizes' => [10, 20, 50, 100],
            'sortable_fields' => [],
            'filters_config' => [],
            'default_page_size' => 20,
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Utilise ce FormType comme DataMapper
        $builder->setDataMapper($this);

        // Search query (Gmail-style q parameter)
        $builder->add('q', TextType::class, [
            'required' => false,
            'mapped' => false, // Handled separately via request query
        ]);

        // Pagination
        $builder
            ->add('page', IntegerType::class, [
                'required' => false,
                'empty_data' => '1',
            ])
            ->add('limit', ChoiceType::class, [
                'required' => false,
                'choices' => array_combine($options['page_sizes'], $options['page_sizes']),
                'empty_data' => (string) $options['default_page_size'],
            ]);

        // Tri - ChoiceType expanded pour radio buttons
        $sortChoices = [];
        $sortChoicesAttributes = [];
        foreach ($options['sortable_fields'] ?? [] as $sortField) {
            foreach (SortDirection::cases() as $sortDirection) {
                $sort = sprintf('%s_%s', $sortField, $sortDirection->value);
                $sortChoices[$sort] = $sort;
                $sortChoicesAttributes[$sort] = [
                    'data-sort-field' => $sortField,
                    'data-sort-direction' => $sortDirection->value,
                ];
            }
        }

        $builder->add('sort', ChoiceType::class, [
            'required' => false,
            'expanded' => true, // Radio buttons style
            'multiple' => false,
            'choices' => $sortChoices,
            'choice_attr' => $sortChoicesAttributes,
            'placeholder' => false,
            'label' => false,
        ]);

        // Add filter fields from filters_config
        foreach ($options['filters_config'] as $fieldName => $config) {
            $type = $config['type'] ?? 'text';
            $label = $config['label'] ?? ucfirst($fieldName);

            $fieldOptions = [
                'required' => false,
                'label' => $label,
                'attr' => [
                    'data-filter-field' => $fieldName,
                ],
            ];

            match ($type) {
                'text' => $builder->add($fieldName, TextType::class, $fieldOptions),
                'enum', 'choice' => $builder->add($fieldName, ChoiceType::class, array_merge($fieldOptions, [
                    'choices' => array_column($config['choices'] ?? [], 'value', 'label'),
                    'placeholder' => $config['placeholder'] ?? 'Tous',
                ])),
                'boolean' => $builder->add($fieldName, ChoiceType::class, array_merge($fieldOptions, [
                    'choices' => ['Oui' => '1', 'Non' => '0'],
                    'placeholder' => 'Tous',
                    'expanded' => true,
                ])),
                default => $builder->add($fieldName, TextType::class, $fieldOptions),
            };
        }
    }

    /**
     * Maps data from the model to form fields.
     */
    public function mapDataToForms(mixed $modelQuery, \Traversable $forms): void
    {
        if (!$modelQuery instanceof ModelQueryDecorator) {
            return;
        }

        $forms = iterator_to_array($forms);

        foreach ($forms as $name => $form) {
            match ($name) {
                'q' => null, // Handled separately, not mapped
                'page' => $form->setData($modelQuery->pager?->page ?? 1),
                'limit' => $form->setData($modelQuery->pager?->nbPerPage ?? 20),
                'sort' => $modelQuery->sorter ? $form->setData(sprintf(
                    '%s_%s',
                    $modelQuery->sorter->field,
                    $modelQuery->sorter->direction->value
                )) : null,
                default => $modelQuery->filters->has($name) ? $form->setData($modelQuery->filters->get($name)) : null,
            };
        }
    }

    /**
     * Maps form fields data back to the model.
     */
    public function mapFormsToData(\Traversable $forms, mixed &$modelQuery): void
    {
        if (!$modelQuery instanceof ModelQueryDecorator) {
            return;
        }

        $forms = iterator_to_array($forms);

        // Map page et limit vers Pager
        $modelQuery->paginate(new Pager(
            $forms['page']->getData() ?? 1,
            $forms['limit']->getData() ?? 20
        ));

        // Map sort vers Sorter
        if (isset($forms['sort']) && $sortString = $forms['sort']->getData()) {
            if (!preg_match('/^(.+)_(asc|desc)$/i', $sortString, $matches)) {
                throw new BadRequestHttpException();
            }

            $modelQuery->sort(new Sorter(
                $matches[1],
                SortDirection::fromString(strtolower($matches[2]))
            ));
        }

        // Filter map
        $filterForms = array_diff_key(
            $forms,
            ['q' => true, 'page' => true, 'limit' => true, 'sort' => true]
        );
        foreach ($filterForms as $name => $form) {
            $formData = $form->getData();
            if (!is_null($formData) && $modelQuery->filters->has($name)) {
                $modelQuery->filters->set($name, $formData);
            }
        }
    }

    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        // Combine filter keys and sortable fields for the fields list
        $filterKeys = $form->getData()->filters->declaredKeys();
        $sortableFields = $options['sortable_fields'] ?? [];

        // Merge and deduplicate - keep original case to match template blocks and sorts keys
        $view->vars['fields'] = array_unique(array_merge($filterKeys, $sortableFields));
        $view->vars['filters'] = array_diff_key(
            $view->children,
            ['q' => true, 'page' => true, 'limit' => true, 'sort' => true]
        );
        $view->vars['sorts'] = [];
        foreach ($options['sortable_fields'] as $sortField) {
            $view->vars['sorts'][$sortField] = [];
            foreach ($view['sort'] as $choice) {
                if ($choice->vars['attr']['data-sort-field'] === $sortField) {
                    $view->vars['sorts'][$sortField][$choice->vars['attr']['data-sort-direction']] = $choice;
                }
            }
        }

        // Pass filters config for the search-filters component
        $view->vars['filters_config'] = $options['filters_config'] ?? [];
    }
}
