<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}{Action}/Event.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?= $Domain ?>\Action\<?= $Model ?><?= $Action ?>;

use Domain\<?= $Domain ?>\Event\<?= $Model ?>Event;

class Event extends <?= $Model ?>Event
{
    public function getResponse(): Response
    {
        return $this->response;
    }
}
