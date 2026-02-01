<?php

namespace Cortex\Bridge\Symfony\Form;

use Cortex\Bridge\Symfony\Module\ModuleLoader;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ModuleTranslationsExtension extends AbstractTypeExtension
{
    /**
     * Returns the list of extended types.
     *
     * @return array The list of extended types
     */
    public static function getExtendedTypes(): iterable
    {
        return [
            FormType::class,
            SubmitType::class,
        ];
    }

    public function __construct(
        private ModuleLoader $moduleLoader,
    ) {
    }

    /**
     * Configures the options for the form type.
     *
     * This method can be overridden to add custom options or modify existing ones.
     *
     * @param OptionsResolver $resolver The resolver for the options
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('module_name', $this->moduleLoader->current);
    }

    /**
     * Builds the form view.
     *
     * This method can be overridden to customize the form view.
     *
     * @param FormView      $view    The form view
     * @param FormInterface $form    The form instance
     * @param array         $options The options for the form
     */
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        // nothing to do with hidden fields
        if ($form->getConfig()->getType()->getInnerType() instanceof HiddenType) {
            return;
        }

        if (empty($options['module_name'])) {
            if (!$parentModule = $form->getParent()?->getConfig()->getOptions()['module_name']) {
                return;
            }

            $options['module_name'] = $parentModule;
        }

        $view->vars['module_name'] = $options['module_name'];

        if (false === $view->vars['label'] || !empty($view->vars['label']) || empty($view->vars['full_name'])) {
            return;
        }
        if (!preg_match('/^([^\[]+)\[(.+)\]$/', $view->vars['full_name'], $m)) {
            return;
        }

        [$_, $prefix, $suffix] = $m;
        $transKey = trim(str_replace(['[', ']'], ['.', ''], $suffix), '.');
        $action = str_replace('_', '.', $prefix);

        $view->vars['translation_domain'] = $options['module_name'];

        foreach (['label', 'label_format', 'help', 'placeholder'] as $key) {
            $view->vars[$key] = sprintf('%s.fields.%s.%s', $action, $transKey, $key);
        }
    }
}
