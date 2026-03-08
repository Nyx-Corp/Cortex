<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Model/{Model}Collection.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/async-collection.md
 */

namespace Domain\<?php echo $Domain; ?>\Model;

use Cortex\Component\Model\ModelCollection;
use Cortex\ValueObject\RegisteredClass;

/**
 * @extends ModelCollection<<?php echo $Model; ?>>
 */
class <?php echo $Model; ?>Collection extends ModelCollection
{
    protected static function expectedType(): ?RegisteredClass
    {
        return new RegisteredClass(<?php echo $Model; ?>::class);
    }
}
