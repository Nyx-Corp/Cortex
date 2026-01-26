<?php

declare(strict_types=1);

namespace Tests\Functional\Application\{Module}\Controller;

use Tests\Functional\Application\{Module}\{Module}WebTestCase;

class {Model}ControllerTest extends {Module}WebTestCase
{
    public function testIndexReturns200(): void
    {
        $client = static::createAppClient();
        $client->request('GET', '/{model}s');

        $this->assertResponseIsSuccessful();
    }

    public function testCreatePageLoads(): void
    {
        $client = static::createAppClient();
        $client->request('GET', '/{model}/create');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    public function testCreateSubmitRedirectsToEdit(): void
    {
        $client = static::createAppClient();
        $crawler = $client->request('GET', '/{model}/create');

        $form = $crawler->selectButton('submit-btn')->form();
        // TODO: Fill required fields
        // $form['{model}_edit[field]'] = 'value';
        $client->submit($form);

        $this->assertResponseRedirects();
        $this->assertMatchesRegularExpression('#/{model}/[a-f0-9-]{36}/edit#', $client->getResponse()->headers->get('Location'));
    }

    public function testCreatedItemAppearsInList(): void
    {
        $client = static::createAppClient();

        // Create an item
        $uuid = $this->createTestItem($client);

        // Verify it appears in the list
        $crawler = $client->request('GET', '/{model}s');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists(sprintf('input[value="%s"]', $uuid));
    }

    public function testEditPageLoadsWithExistingItem(): void
    {
        $client = static::createAppClient();

        $uuid = $this->createTestItem($client);

        $crawler = $client->request('GET', sprintf('/{model}/%s/edit', $uuid));
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    // Uncomment if model uses Archivable trait:
    // public function testArchiveRemovesFromDefaultList(): void
    // {
    //     $client = static::createAppClient();
    //
    //     $uuid = $this->createTestItem($client);
    //
    //     // Archive
    //     $client->request('GET', sprintf('/{model}/%s/archive', $uuid));
    //     $this->assertResponseRedirects();
    //
    //     // Verify absent from default list
    //     $crawler = $client->request('GET', '/{model}s');
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
    //     $client->request('GET', sprintf('/{model}/%s/archive', $uuid));
    //
    //     // Verify present with archivedAt:true filter
    //     $crawler = $client->request('GET', '/{model}s?q=archivedAt:true');
    //     $this->assertResponseIsSuccessful();
    //     $this->assertSelectorExists(sprintf('input[value="%s"]', $uuid));
    // }

    /**
     * Helper: Create a test item and return its UUID.
     */
    private function createTestItem($client): string
    {
        $crawler = $client->request('GET', '/{model}/create');
        $form = $crawler->selectButton('submit-btn')->form();
        // TODO: Fill required fields
        // $form['{model}_edit[field]'] = 'value';
        $client->submit($form);

        preg_match('#/([a-f0-9-]{36})/edit#', $client->getResponse()->headers->get('Location'), $matches);

        return $matches[1];
    }
}
