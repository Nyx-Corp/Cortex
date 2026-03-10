<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Persistence/{Model}Store.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/index.md
 */

namespace Domain\<?php echo $Domain; ?>\Persistence;

use Cortex\Component\Model\Attribute\Model;
use Cortex\Component\Model\Store\ModelStore;
use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>;

#[Model(<?php echo $Model; ?>::class)]
class <?php echo $Model; ?>Store extends ModelStore
{
}
