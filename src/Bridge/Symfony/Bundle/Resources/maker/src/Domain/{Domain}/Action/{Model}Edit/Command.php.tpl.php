<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}Edit/Command.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?= $Domain ?>\Action\<?= $Model ?>Edit;

use Domain\<?= $Domain ?>\Model\<?= $Model ?>;
use Symfony\Component\Uid\Uuid;

class Command
{
    /**
     * Define editable properties here
     */
    public function __construct(
        // public readonly string $label,
        public readonly ?Uuid $uuid = null,
    ) {
    }
}
