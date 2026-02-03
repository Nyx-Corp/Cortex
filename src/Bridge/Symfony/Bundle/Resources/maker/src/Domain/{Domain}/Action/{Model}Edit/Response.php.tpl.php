<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}Edit/Response.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?= $Domain ?>\Action\<?= $Model ?>Edit;

use Domain\<?= $Domain ?>\Model\<?= $Model ?>;

class Response
{
    public function __construct(
        public readonly <?= $Model ?> $<?= $model ?>,
    ) {
    }

    public function isSuccess(): bool
    {
        return true;
    }
}
