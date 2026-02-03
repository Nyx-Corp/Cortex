<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Model/{Model}Collection.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/async-collection.md
 */

namespace Domain\<?= $Domain ?>\Model;

use Cortex\Component\Model\ModelCollection;
use Cortex\ValueObject\RegisteredClass;

/**
 * @extends ModelCollection<<?= $Model ?>>
 */
class <?= $Model ?>Collection extends ModelCollection
{
    protected static function expectedType(): ?RegisteredClass
    {
        return new RegisteredClass(<?= $Model ?>::class);
    }
}
