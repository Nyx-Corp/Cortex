<?= "<?php\n" ?>

/**
 * @generated from src/Lib/Cortex/src/Bridge/Symfony/Bundle/Resources/maker/tests/Functional/Application/{Module}/Controller/{Model}ControllerTest.php.tpl.php
 * @see src/Lib/Cortex/README.md
 * @see src/Lib/Cortex/docs/makers.md
 */

declare(strict_types=1);

namespace Tests\Functional\Application\<?= $Module ?>\Controller;

use Tests\Functional\Application\<?= $Module ?>\<?= $Module ?>WebTestCase;

class <?= $Model ?>ControllerTest extends <?= $Module ?>WebTestCase
{
    /**
     * IMPORTANT: Replace 'label' with the actual field name used for display (name, title, firstname, etc.)
     * This field is used in testEditFromListAndVerifyChanges to verify the edit flow.
     */
    private const LABEL_FIELD = 'label';

    /**
     * IMPORTANT: Replace 'label' with the actual filter field name for search (may differ from form field)
     * Example: 'name', 'event_title', 'contact_firstname'
     */
    private const LABEL_FILTER = 'label';

    protected function setUp(): void
    {
        parent::setUp();

        // WARNING: Emit E_USER_WARNING if developer forgot to replace placeholder field names
        if (self::LABEL_FIELD === 'label' || self::LABEL_FILTER === 'label') {
            @trigger_error(
                sprintf(
                    '%s: LABEL_FIELD and/or LABEL_FILTER constants are still set to "label". ' .
                    'Replace them with actual field names (e.g., "name", "title", "firstname") for proper testing.',
                    static::class
                ),
                \E_USER_WARNING
            );
        }
    }

    public function testIndexReturns200(): void
    {
        $client = static::createAppClient();
        $client->request('GET', '/<?= $model ?>s');

        $this->assertResponseIsSuccessful();
    }

    public function testCreatePageLoads(): void
    {
        $client = static::createAppClient();
        $client->request('GET', '/<?= $model ?>/create');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testCreateSubmitRedirectsToEdit(): void
    {
        $client = static::createAppClient();
        $crawler = $client->request('GET', '/<?= $model ?>/create');

        $form = $crawler->selectButton('submit-btn')->form();
        // TODO: Fill required fields
        // $form['<?= $model ?>_edit[field]'] = 'value';
        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertMatchesRegularExpression('#/<?= $model ?>/[a-f0-9-]{36}/edit#', $client->getResponse()->headers->get('Location'));
    }

    public function testCreatedItemAppearsInList(): void
    {
        $client = static::createAppClient();

        // Create an item
        $uuid = $this->createTestItem($client);

        // Verify it appears in the list
        $crawler = $client->request('GET', '/<?= $model ?>s');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(sprintf('input[value="%s"]', $uuid));
    }

    public function testEditPageLoadsWithExistingItem(): void
    {
        $client = static::createAppClient();

        $uuid = $this->createTestItem($client);

        $crawler = $client->request('GET', sprintf('/<?= $model ?>/%s/edit', $uuid));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testFilterByUuidDisplaysActiveFilterChip(): void
    {
        $client = static::createAppClient();

        // Create an item and filter by its UUID
        $uuid = $this->createTestItem($client);
        $crawler = $client->request('GET', sprintf('/<?= $model ?>s?q=uuid:%s', $uuid));

        $this->assertResponseIsSuccessful();

        // Verify the active filter chip is displayed with remove button
        $this->assertSelectorExists('[data-action*="removeFilter"][data-filter-field="uuid"]');

        // Verify the UUID is displayed in the chip (parent contains the value)
        $chipText = $crawler->filter('[data-action*="removeFilter"][data-filter-field="uuid"]')->ancestors()->first()->text();
        $this->assertStringContainsString($uuid, $chipText);
    }

    /**
     * Tests the complete edit flow from list:
     * List -> Click edit -> Verify data -> Modify -> Return -> Verify in list -> Search -> Verify
     */
    public function testEditFromListAndVerifyChanges(): void
    {
        $client = static::createAppClient();

        // 1. Create an item with a unique value
        $initialLabel = 'Initial-' . uniqid();
        $uuid = $this->createTestItem($client, $initialLabel);

        // 2. Load the list and click the edit link
        $crawler = $client->request('GET', '/<?= $model ?>s');
        $editLink = $crawler->filter(sprintf('a[href*="/%s/edit"]', $uuid));
        $this->assertCount(1, $editLink, 'Edit link should exist in list');
        $client->click($editLink->link());

        // 3. Verify the form contains the correct data
        $crawler = $client->getCrawler();
        $this->assertResponseIsSuccessful();
        $labelField = $crawler->filter('#submit-btn')->form()->get('<?= $model ?>_edit[' . self::LABEL_FIELD . ']');
        $this->assertEquals($initialLabel, $labelField->getValue());

        // 4. Modify the value
        $modifiedLabel = 'Modified-' . uniqid();
        $form = $crawler->filter('#submit-btn')->form();
        $form['<?= $model ?>_edit[' . self::LABEL_FIELD . ']'] = $modifiedLabel;
        $client->submit($form);

        // 5. Edit form typically stays on the same page (no redirect)
        // Verify success and navigate to list (via breadcrumb or any link to list)
        $this->assertResponseIsSuccessful();
        $crawler = $client->getCrawler();

        $listLink = $crawler->filter('a[href$="/<?= $model ?>s"]');
        $this->assertGreaterThan(0, $listLink->count(), 'Link to list should exist');
        $client->click($listLink->first()->link());

        // 6. Verify the modified value appears in the list
        $crawler = $client->getCrawler();
        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString($modifiedLabel, $crawler->filter('table tbody')->text());

        // 7. Search via the search bar
        $crawler = $client->request('GET', sprintf('/<?= $model ?>s?q=%s:%s', self::LABEL_FILTER, $modifiedLabel));
        $this->assertResponseIsSuccessful();

        // 8. Verify the item appears in search results
        $this->assertSelectorExists(sprintf('input[value="%s"]', $uuid));
        $this->assertStringContainsString($modifiedLabel, $crawler->filter('table tbody')->text());
    }

    // Uncomment if model uses Archivable trait:
    // public function testArchiveRemovesFromDefaultList(): void
    // {
    //     $client = static::createAppClient();
    //
    //     $uuid = $this->createTestItem($client);
    //
    //     // Archive
    //     $client->request('GET', sprintf('/<?= $model ?>/%s/archive', $uuid));
    //     $this->assertResponseRedirects();
    //
    //     // Verify absent from default list
    //     $crawler = $client->request('GET', '/<?= $model ?>s');
    //     $this->assertSelectorNotExists(sprintf('input[value="%s"]', $uuid));
    // }
    //
    // public function testArchivedItemAppearsWithFilter(): void
    // {
    //     $client = static::createAppClient();
    //
    //     $uuid = $this->createTestItem($client);
    //
    //     // Archive
    //     $client->request('GET', sprintf('/<?= $model ?>/%s/archive', $uuid));
    //
    //     // Verify present with archivedAt:true filter
    //     $crawler = $client->request('GET', '/<?= $model ?>s?q=archivedAt:true');
    //     $this->assertResponseIsSuccessful();
    //     $this->assertSelectorExists(sprintf('input[value="%s"]', $uuid));
    // }

    /**
     * Helper: Create a test item and return its UUID.
     *
     * @param string|null $label The label/name value for the item (uses LABEL_FIELD constant)
     */
    private function createTestItem($client, ?string $label = null): string
    {
        $label = $label ?? 'Test-' . uniqid();

        $crawler = $client->request('GET', '/<?= $model ?>/create');
        $form = $crawler->selectButton('submit-btn')->form();
        // TODO: Fill any additional required fields
        $form['<?= $model ?>_edit[' . self::LABEL_FIELD . ']'] = $label;
        $client->submit($form);

        preg_match('#/([a-f0-9-]{36})/edit#', $client->getResponse()->headers->get('Location'), $matches);

        return $matches[1];
    }
}
