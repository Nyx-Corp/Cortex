<?php echo "<?php\n"; ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/src/Application/{Module}/Controller/Action/{Model}ListAction.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/bridge-symfony.md
 */

namespace Application\<?php echo $Module; ?>\Controller\Action<?php echo $subpath_namespace ?? ''; ?>;

use Cortex\Bridge\Symfony\Controller\ControllerInterface;
use Cortex\Bridge\Symfony\Model\Query\ModelQueryDecorator;
use Domain\<?php echo $Domain; ?>\Factory\<?php echo $Model; ?>Factory;
use Domain\<?php echo $Domain; ?>\Model\<?php echo $Model; ?>Collection;
use Symfony\Component\Form\Extension\Core\Type as Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Displays <?php echo $Model; ?>s as a list using Cortex decorator for sorting, filtering, and pagination.
 *
 * Route derived by convention: GET /{<?php echo $model; ?>s} → {<?php echo $model; ?>}/index
 */
class <?php echo $Model; ?>ListAction implements ControllerInterface
{
    public function __construct(
        private readonly <?php echo $Model; ?>Factory $<?php echo $model; ?>Factory,
    ) {
    }

    /**
     * @return array<string, mixed>|Response
     */
    public function __invoke(<?php echo $Model; ?>Collection $<?php echo $model; ?>s): array|Response
    {
        /** @var ModelQueryDecorator $query */
        $query = $<?php echo $model; ?>s->query;

        // Filter non-archived by default (if model uses Archivable trait)
        // The Gmail-style query parser allows overriding with ?q=archivedAt:true
        // $query->filter(archivedAt: null);

        $query->decorate(
            // Define sortable columns (must match template _th/_td blocks)
            sortables: [/* 'firstname', 'lastname' */],
            filters: fn (FormBuilderInterface $filtersFormBuilder) => $filtersFormBuilder
                // ->add('firstname', Form\TextType::class)
                // ->add('lastname', Form\TextType::class)
                // If model uses Archivable trait, add this filter for ?q=archivedAt:true support:
                // ->add('archivedAt', Form\CheckboxType::class, ['required' => false, 'label' => 'Archiv<?php echo "\xc3\xa9"; ?>'])
        );

        return [
            'collection' => $<?php echo $model; ?>s->toArray(),
            'form' => $query->getDecorator(),
            'pager' => $query->pager,
        ];
    }
}
