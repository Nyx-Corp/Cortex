<?php

namespace Cortex\Bridge\Symfony\Form;

use Cortex\Component\Action\ActionHandlerCollection;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandMapperExtension extends AbstractTypeExtension
{
    /**
     * @param array<class-string, class-string> $actionAttributeMapping formTypeClass => commandClass
     */
    public function __construct(
        private ActionHandlerCollection $handlerCollection,
        private readonly array $actionAttributeMapping = [],
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [
            FormType::class,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'command_class' => null,
            'data_class' => null,
        ]);
        $resolver->setAllowedValues(
            'command_class',
            [null, ...$this->handlerCollection->getRegisteredCommands()]
        );
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
                $form = $event->getForm();
                $commandClass = $this->resolveCommandClass($form);
                if (!$commandClass) {
                    return;
                }

                $data = $event->getData();
                if (empty($data) || is_array($data)) {
                    return;
                }

                $event->setData(get_object_vars($data));
            })
            ->addEventListener(FormEvents::SUBMIT, function (FormEvent $event) {
                $form = $event->getForm();
                $commandClass = $this->resolveCommandClass($form);
                if (!$commandClass) {
                    return;
                }

                // Filter data to only include parameters accepted by the command constructor
                $data = $event->getData();
                $reflection = new \ReflectionClass($commandClass);
                $constructor = $reflection->getConstructor();
                $allowedParams = [];
                if ($constructor) {
                    foreach ($constructor->getParameters() as $param) {
                        $allowedParams[] = $param->getName();
                    }
                }
                $filteredData = array_intersect_key($data, array_flip($allowedParams));

                $result = $this->handlerCollection->handleCommand(
                    new $commandClass(...$filteredData)
                );

                $event->setData($result);
            })
        ;
    }

    private function resolveCommandClass(\Symfony\Component\Form\FormInterface $form): ?string
    {
        // 1. Explicit option (legacy + primary)
        $commandClass = $form->getConfig()->getOption('command_class');
        if ($commandClass) {
            return $commandClass;
        }

        // 2. #[Action] attribute mapping (injected by compiler pass)
        $formType = $form->getConfig()->getType()->getInnerType();
        $formTypeClass = $formType::class;

        return $this->actionAttributeMapping[$formTypeClass] ?? null;
    }
}
