<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Model/{Model}.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/index.md
 */

namespace Domain\<?= $Domain ?>\Model;

use Cortex\Component\Model\Archivable;
use Cortex\Component\Model\Uuidentifiable;
use Symfony\Component\Uid\Uuid;

class <?= $Model ?> implements \Stringable
{
    use Uuidentifiable;
    use Archivable;

    public function __construct(
        // public readonly mixed $property
        ?Uuid $uuid = null,
    ) {
        $this->uuid = $uuid;
    }

    public function __toString(): string
    {
        return (string) $this->uuid;
    }
}
