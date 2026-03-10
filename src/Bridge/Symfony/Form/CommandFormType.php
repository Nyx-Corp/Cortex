<?php

namespace Cortex\Bridge\Symfony\Form;

use Cortex\Bridge\Symfony\Form\DataTransformer\ModelTransformer;
use Cortex\Component\Model\Factory\ModelFactory;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandFormType extends AbstractType
{
    /**
     * @param array<string, array<string, array{type: string, options: array<string, mixed>, factory_id?: string}>> $fieldsConfig
     * @param array<string, ModelFactory>                                                                           $factoryMapping modelClass => factory
     */
    public function __construct(
        private readonly array $fieldsConfig,
        private readonly array $factoryMapping = [],
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('command_class');
        $resolver->setAllowedTypes('command_class', 'string');
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $commandClass = $options['command_class'];

        if (!isset($this->fieldsConfig[$commandClass])) {
            return;
        }

        foreach ($this->fieldsConfig[$commandClass] as $name => $field) {
            $builder->add($name, $field['type'], $field['options']);

            // Add ModelTransformer for model fields
            if (isset($field['model_class']) && isset($this->factoryMapping[$field['model_class']])) {
                $builder->get($name)->addModelTransformer(
                    new ModelTransformer($this->factoryMapping[$field['model_class']])
                );
            }
        }
    }
}
