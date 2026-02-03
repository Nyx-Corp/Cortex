<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Error/{Domain}Exception.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/index.md
 */

namespace Domain\<?= $Domain ?>\Error;

use Cortex\Component\Exception\DomainException;

/**
 * Base exception for <?= $Domain ?> domain.
 */
class <?= $Domain ?>Exception extends \Exception implements DomainException
{
    public function getDomain(): string
    {
        return '<?= $domain ?>';
    }
}
