<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}Archive/Command.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?= $Domain ?>\Action\<?= $Model ?>Archive;

use Domain\<?= $Domain ?>\Model\<?= $Model ?>;

class Command
{
    public function __construct(
        public readonly <?= $Model ?> $<?= $model ?>,
        public readonly bool $isArchived = true,
    ) {
    }
}
