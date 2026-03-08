<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Factory/{Model}Factory.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/index.md
 */

namespace Domain\<?php echo $Domain; ?>\Factory;

use Cortex\Component\Model\Attribute\Model;
use Cortex\Component\Model\Factory\ModelFactory;
use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>;

#[Model(<?php echo $Model; ?>::class)]
class <?php echo $Model; ?>Factory extends ModelFactory
{
}
