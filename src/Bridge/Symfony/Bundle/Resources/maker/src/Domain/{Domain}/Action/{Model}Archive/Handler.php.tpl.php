<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Domain/{Domain}/Action/{Model}Archive/Handler.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/events.md
 */

namespace Domain\<?= $Domain ?>\Action\<?= $Model ?>Archive;

use Cortex\Component\Action\ActionHandler;
use Cortex\Component\Date\DateTimeFactory;
use Cortex\Component\Event\EmitsActionEvents;
use Cortex\Component\Event\EventDispatcherAwareInterface;
use Domain\<?= $Domain ?>\Persistence\<?= $Model ?>Store;

class Handler implements ActionHandler, EventDispatcherAwareInterface
{
    use EmitsActionEvents;

    public function __construct(
        private DateTimeFactory $dateTimeFactory,
        private <?= $Model ?>Store $store,
    ) {
    }

    public function __invoke(Command $command): Response
    {
        /** @var \Domain\<?= $Domain ?>\Model\<?= $Model ?> $model */
        $model = $command-><?= $model ?>;

        if ($command->isArchived) {
            $model->archive($this->dateTimeFactory->now());
        } else {
            $model->restore();
        }

        $this->store->sync($model);

        $this->emit($event = new Event(new Response(
            $model,
            $model->isArchived() == $command->isArchived
        )));

        return $event->getResponse();
    }
}
