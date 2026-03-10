<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}Update/Command.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Update;

use Symfony\Component\Uid\Uuid;

class Command
{
    /**
     * Define editable properties here.
     */
    public function __construct(
        public readonly Uuid $uuid,
        // public readonly string $label,
    ) {
    }
}
