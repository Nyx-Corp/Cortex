<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}Edit/Handler.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?>Edit;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Event\EmitsActionEvents;
use Cortex\Component\Event\EventDispatcherAwareInterface;
use Domain\<?php echo $Domain; ?>\Persistence\<?php echo $Model; ?>Store;
use Domain\<?php echo $Domain; ?>\Factory\<?php echo $Model; ?>Factory;

class Handler implements ActionHandler, EventDispatcherAwareInterface
{
    use EmitsActionEvents;

    public function __construct(
        private readonly <?php echo $Model; ?>Factory $factory,
        private readonly <?php echo $Model; ?>Store $store,
    ) {
    }

    /**
     * Handles <?php echo $Model; ?> edition through factory as a new instance.
     * If inner model has inner state, consider not using this handler, make one by state transition.
     */
    public function __invoke(Command $command): Response
    {
        $model = $this->factory->create()
            ->with(...get_object_vars($command))
            ->build()
        ;

        $this->store->sync($model);

        $this->emit($event = new Event(new Response($model)));

        return $event->getResponse();
    }
}
