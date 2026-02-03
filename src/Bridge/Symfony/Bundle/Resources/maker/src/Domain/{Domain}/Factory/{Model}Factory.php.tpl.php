<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Factory/{Model}Factory.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/index.md
 */

namespace Domain\<?= $Domain ?>\Factory;

use Cortex\Component\Model\Attribute\Model;
use Cortex\Component\Model\Factory\ModelFactory;
use Domain\<?= $Domain ?>\Model\<?= $Model ?>;

#[Model(<?= $Model ?>::class)]
class <?= $Model ?>Factory extends ModelFactory
{
}
