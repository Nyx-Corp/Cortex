<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Error/{Domain}Exception.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/index.md
 */

namespace Domain\<?php echo $Domain; ?>\Error;

use Cortex\Component\Exception\DomainException;

/**
 * Base exception for <?php echo $Domain; ?> domain.
 */
class <?php echo $Domain; ?>Exception extends \Exception implements DomainException
{
    public function getDomain(): string
    {
        return '<?php echo $domain; ?>';
    }
}
