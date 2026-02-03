<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}{Action}/Exception.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?= $Domain ?>\Action\<?= $Model ?><?= $Action ?>;

use Domain\<?= $Domain ?>\Error\<?= $Model ?>Exception;

class Exception extends <?= $Model ?>Exception
{
}
