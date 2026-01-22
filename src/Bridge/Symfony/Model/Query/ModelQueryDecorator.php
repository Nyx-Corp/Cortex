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

    public function build(FormFactoryInterface $formFactory, Request $request): self
    {
        $this->formFactory = $formFactory;
        $this->request = $request;

        return $this;
    }

    public function decorate(array $sortables, ?\Closure $filters = null): self
    {
        if (!$this->request) {
            throw new \BadMethodCallException('ModelQuery cannot be decorated without building it first, using build() method.');
        }

        $this->formBuilder = $this->formFactory->createNamedBuilder(
            '',
            ModelQueryType::class,
            $this,
            ['sortable_fields' => $sortables]
        );
        if ($filters) {
            $filters($this->formBuilder);
        }

        return $this;
    }

    public function resolve(): \Generator
    {
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
        }

        // Posted filter form
        if ($this->formBuilder) {
            $this->form = $this->formBuilder->getForm();

            $this->form->handleRequest($this->request);
            if ($this->form->isSubmitted() && !$this->form->isValid()) {
                throw new BadRequestException(implode(' ; ', array_map(fn (FormError $error) => sprintf('%s : %s', $error->getOrigin()->getName(), $error->getMessage()), iterator_to_array($this->form->getErrors(true, true)))));
            }
        }

        return parent::resolve();
    }

    public function getDecorator(): FormView
    {
        if (!$this->form) {
            throw new \BadMethodCallException('No decorator defined, query has to be resolved first.');
        }

        return $this->form->createView();
    }
}
