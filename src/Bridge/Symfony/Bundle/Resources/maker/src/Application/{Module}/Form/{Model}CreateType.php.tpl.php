<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Form/{Model}CreateType.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Form;

use Cortex\Bridge\Symfony\Form\Attribute\Action;
use Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Create\Command;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type as Form;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * @extends AbstractType<mixed>
 */
#[Action(Command::class)]
class <?php echo $Model; ?>CreateType extends AbstractType
{
    /**
     * @param array<string, mixed> $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // $builder
        //     ->add('label', Form\TextType::class)
        // ;
    }
}
