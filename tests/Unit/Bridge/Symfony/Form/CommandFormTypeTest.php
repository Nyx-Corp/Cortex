<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Symfony\Form;

use Cortex\Bridge\Symfony\Form\Attribute\Action;
use Cortex\Bridge\Symfony\Form\CommandFormType;
use PHPUnit\Framework\TestCase;

class CommandFormTypeTest extends TestCase
{
    public function testFieldsConfigAppliedCorrectly(): void
    {
        $fieldsConfig = [
            'TestCommand' => [
                'name' => [
                    'type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
                    'options' => ['required' => true],
                ],
                'count' => [
                    'type' => 'Symfony\Component\Form\Extension\Core\Type\IntegerType',
                    'options' => ['required' => false],
                ],
            ],
        ];

        $formType = new CommandFormType($fieldsConfig);

        $this->assertInstanceOf(CommandFormType::class, $formType);
    }

    public function testActionAttributeHoldsCommandClass(): void
    {
        $action = new Action('App\Domain\Test\Command');

        $this->assertSame('App\Domain\Test\Command', $action->commandClass);
    }

    public function testActionAttributeIsTargetClass(): void
    {
        $ref = new \ReflectionClass(Action::class);
        $attributes = $ref->getAttributes(\Attribute::class);

        $this->assertNotEmpty($attributes);
    }
}
