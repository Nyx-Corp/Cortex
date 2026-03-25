<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Symfony\Api;

use Cortex\Bridge\Symfony\Api\OpenApiGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(OpenApiGenerator::class)]
class OpenApiGeneratorTest extends TestCase
{
    // =======================================================================
    // SPEC STRUCTURE TESTS
    // =======================================================================

    public function testGeneratesValidOpenApiStructure(): void
    {
        $generator = $this->createGenerator([]);
        $spec = $generator->generate();

        $this->assertSame('3.1.0', $spec['openapi']);
        $this->assertArrayHasKey('info', $spec);
        $this->assertArrayHasKey('servers', $spec);
        $this->assertArrayHasKey('paths', $spec);
        $this->assertArrayHasKey('components', $spec);
        $this->assertArrayHasKey('security', $spec);
    }

    public function testInfoVersionMatchesApiVersion(): void
    {
        $generator = $this->createGenerator([]);

        $this->assertSame('1.0.0', $generator->generate(1)['info']['version']);
        $this->assertSame('3.0.0', $generator->generate(3)['info']['version']);
    }

    public function testServerUrlIncludesVersionPrefix(): void
    {
        $generator = $this->createGenerator([]);

        $servers = $generator->generate(1)['servers'];
        $this->assertSame('/api/v1', $servers[0]['url']);

        $servers = $generator->generate(2, 'https://api.example.com')['servers'];
        $this->assertSame('https://api.example.com/v2', $servers[0]['url']);
    }

    public function testIncludesBearerSecurityScheme(): void
    {
        $generator = $this->createGenerator([]);
        $spec = $generator->generate();

        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
        $this->assertSame('http', $spec['components']['securitySchemes']['bearerAuth']['type']);
        $this->assertSame('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
    }

    public function testIncludesErrorSchemas(): void
    {
        $generator = $this->createGenerator([]);
        $spec = $generator->generate();
        $schemas = $spec['components']['schemas'];

        $this->assertArrayHasKey('ValidationError', $schemas);
        $this->assertArrayHasKey('DomainError', $schemas);
        $this->assertArrayHasKey('AuthError', $schemas);
    }

    // =======================================================================
    // PATH GENERATION TESTS
    // =======================================================================

    public function testCreateActionGeneratesPostPath(): void
    {
        $generator = $this->createGenerator([
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
        ]);

        $spec = $generator->generate();

        $this->assertArrayHasKey('/account/account', $spec['paths']);
        $this->assertArrayHasKey('post', $spec['paths']['/account/account']);
    }

    public function testUpdateActionGeneratesPutPath(): void
    {
        $generator = $this->createGenerator([
            'Domain\\Account\\Action\\AccountUpdate\\Command' => $this->meta('Account', 'Account', 'Update'),
        ]);

        $spec = $generator->generate();

        $this->assertArrayHasKey('/account/account/{uuid}', $spec['paths']);
        $this->assertArrayHasKey('put', $spec['paths']['/account/account/{uuid}']);
    }

    public function testArchiveActionGeneratesDeletePath(): void
    {
        $generator = $this->createGenerator([
            'Domain\\Account\\Action\\AccountArchive\\Command' => $this->meta('Account', 'Account', 'Archive'),
        ]);

        $spec = $generator->generate();

        $this->assertArrayHasKey('/account/account/{uuid}', $spec['paths']);
        $this->assertArrayHasKey('delete', $spec['paths']['/account/account/{uuid}']);
    }

    public function testCustomActionGeneratesPostWithActionSuffix(): void
    {
        $generator = $this->createGenerator([
            'Domain\\Catalog\\Action\\ProductSync\\Command' => $this->meta('Catalog', 'Product', 'Sync'),
        ]);

        $spec = $generator->generate();

        $this->assertArrayHasKey('/catalog/product/{uuid}/sync', $spec['paths']);
        $this->assertArrayHasKey('post', $spec['paths']['/catalog/product/{uuid}/sync']);
    }

    public function testPathWithUuidIncludesUuidParameter(): void
    {
        $generator = $this->createGenerator([
            'Domain\\Account\\Action\\AccountUpdate\\Command' => $this->meta('Account', 'Account', 'Update'),
        ]);

        $spec = $generator->generate();
        $operation = $spec['paths']['/account/account/{uuid}']['put'];

        $this->assertCount(1, $operation['parameters']);
        $this->assertSame('uuid', $operation['parameters'][0]['name']);
        $this->assertSame('path', $operation['parameters'][0]['in']);
        $this->assertTrue($operation['parameters'][0]['required']);
    }

    // =======================================================================
    // OPERATION DETAILS TESTS
    // =======================================================================

    public function testOperationHasCorrectStructure(): void
    {
        $generator = $this->createGenerator([
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
        ]);

        $spec = $generator->generate();
        $operation = $spec['paths']['/account/account']['post'];

        $this->assertArrayHasKey('operationId', $operation);
        $this->assertArrayHasKey('summary', $operation);
        $this->assertArrayHasKey('tags', $operation);
        $this->assertArrayHasKey('responses', $operation);
    }

    public function testOperationIdFollowsConvention(): void
    {
        $generator = $this->createGenerator([
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
        ]);

        $spec = $generator->generate();
        $operation = $spec['paths']['/account/account']['post'];

        $this->assertSame('account_account_create', $operation['operationId']);
    }

    public function testOperationHasStandardResponses(): void
    {
        $generator = $this->createGenerator([
            'Domain\\Account\\Action\\AccountCreate\\Command' => $this->meta('Account', 'Account', 'Create'),
        ]);

        $spec = $generator->generate();
        $responses = $spec['paths']['/account/account']['post']['responses'];

        $this->assertArrayHasKey('200', $responses);
        $this->assertArrayHasKey('400', $responses);
        $this->assertArrayHasKey('401', $responses);
        $this->assertArrayHasKey('422', $responses);
    }

    public function testOperationTagMatchesDomain(): void
    {
        $generator = $this->createGenerator([
            'Domain\\Studio\\Action\\ShootingCreate\\Command' => $this->meta('Studio', 'Shooting', 'Create'),
        ]);

        $spec = $generator->generate();
        $operation = $spec['paths']['/studio/shooting']['post'];

        $this->assertSame(['Studio'], $operation['tags']);
    }

    // =======================================================================
    // VERSION FILTERING TESTS
    // =======================================================================

    public function testFiltersOutActionsNotAvailableInVersion(): void
    {
        $generator = $this->createGenerator([
            'Domain\\A\\Action\\FooCreate\\Command' => $this->meta('A', 'Foo', 'Create', apiSince: 1),
            'Domain\\A\\Action\\BarCreate\\Command' => $this->meta('A', 'Bar', 'Create', apiSince: 3),
        ]);

        $v1Spec = $generator->generate(1);
        $v3Spec = $generator->generate(3);

        $this->assertCount(1, $v1Spec['paths']); // Only Foo
        $this->assertCount(2, $v3Spec['paths']); // Foo and Bar
    }

    public function testMarksDeprecatedOperations(): void
    {
        $generator = $this->createGenerator([
            'Domain\\A\\Action\\FooUpdate\\Command' => $this->meta('A', 'Foo', 'Update', apiSince: 1, apiDeprecated: 2, apiSunset: '2026-06-01'),
        ]);

        $v1Spec = $generator->generate(1);
        $v2Spec = $generator->generate(2);

        $v1Op = $v1Spec['paths']['/a/foo/{uuid}']['put'];
        $v2Op = $v2Spec['paths']['/a/foo/{uuid}']['put'];

        $this->assertArrayNotHasKey('deprecated', $v1Op);
        $this->assertTrue($v2Op['deprecated']);
        $this->assertSame('2026-06-01', $v2Op['x-sunset']);
    }

    public function testDeprecatedWithoutSunsetOmitsXSunset(): void
    {
        $generator = $this->createGenerator([
            'Domain\\A\\Action\\FooUpdate\\Command' => $this->meta('A', 'Foo', 'Update', apiSince: 1, apiDeprecated: 2),
        ]);

        $spec = $generator->generate(2);
        $op = $spec['paths']['/a/foo/{uuid}']['put'];

        $this->assertTrue($op['deprecated']);
        $this->assertArrayNotHasKey('x-sunset', $op);
    }

    public function testEmptyMetadataProducesEmptyPaths(): void
    {
        $generator = $this->createGenerator([]);
        $spec = $generator->generate();

        $this->assertEmpty($spec['paths']);
    }

    // =======================================================================
    // HELPERS
    // =======================================================================

    private function createGenerator(array $metadata): OpenApiGenerator
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $translator = $this->createMock(TranslatorInterface::class);

        // Translator returns the key as-is (triggers fallback summary)
        $translator->method('trans')->willReturnArgument(0);

        // FormFactory returns a form with an empty view (no fields)
        $form = $this->createMock(FormInterface::class);
        $view = new FormView();
        $view->children = [];
        $form->method('createView')->willReturn($view);
        $formFactory->method('create')->willReturn($form);

        return new OpenApiGenerator($metadata, $formFactory, $translator);
    }

    private function meta(
        string $domain,
        string $model,
        string $action,
        int $apiSince = 1,
        ?int $apiDeprecated = null,
        ?string $apiSunset = null,
    ): array {
        return [
            'commandClass' => sprintf('Domain\\%s\\Action\\%s%s\\Command', $domain, $model, $action),
            'domain' => $domain,
            'model' => $model,
            'action' => $action,
            'formType' => 'App\\Form\\'.$model.$action.'Type',
            'apiSince' => $apiSince,
            'apiDeprecated' => $apiDeprecated,
            'apiSunset' => $apiSunset,
        ];
    }
}
