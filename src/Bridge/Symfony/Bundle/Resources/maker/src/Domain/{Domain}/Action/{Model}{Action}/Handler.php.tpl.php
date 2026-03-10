<?php echo "<?php\n"; ?>

namespace Domain\<?php echo $Domain; ?>\Action\<?php echo $Model; ?><?php echo $Action; ?>;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Event\EmitsActionEvents;
use Cortex\Component\Event\EventDispatcherAwareInterface;
use Domain\<?php echo $Domain; ?>\Persistence\<?php echo $Model; ?>Store;

class Handler implements ActionHandler, EventDispatcherAwareInterface
{
    use EmitsActionEvents;

    public function __construct(
        private <?php echo $Model; ?>Store $store,
    ) {
    }

    public function __invoke(Command $command): Response
    {
        /** @var \Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?> $model */
        $model = $command-><?php echo $model; ?>;

        // do things

        $this->store->sync($model);

        $this->emit($event = new Event(new Response($model)));

        return $event->getResponse();
    }
}
