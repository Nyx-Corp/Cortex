<?php

namespace Application\{Module}\Form;

use Domain\{Domain}\Action\{Model}Edit\Command;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class {Model}EditType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'command_class' => Command::class,
            'translation_domain' => '{model}',
        ]);
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // $builder
        //     ->add('label', Form\TextType::class)
        // ;
    }
}
