<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}Archive/Event.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Archive;

use Domain\<?php echo $Domain; ?>\Event\<?php echo $Model; ?>Event;

class Event extends <?php echo $Model; ?>Event
{
    public function getResponse(): Response
    {
        return $this->response;
    }
}
