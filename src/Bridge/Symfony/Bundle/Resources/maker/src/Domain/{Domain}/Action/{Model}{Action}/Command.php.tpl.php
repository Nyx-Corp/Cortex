<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}{Action}/Command.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?><?php echo $Action; ?>;

use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>;

class Command
{
    public function __construct(
        public readonly <?php echo $Model; ?> $<?php echo $model; ?>,
    ) {
    }
}
