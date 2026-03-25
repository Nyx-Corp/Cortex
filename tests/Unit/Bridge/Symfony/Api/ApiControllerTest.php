<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Symfony\Api;

use Cortex\Bridge\Symfony\Api\ApiController;
use Cortex\Bridge\Symfony\Api\VersionTransformerCollection;
use Cortex\Bridge\Symfony\Api\VersionTransformerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ApiController::class)]
class ApiControllerTest extends TestCase
{
    private FormFactoryInterface $formFactory;
    private VersionTransformerCollection $transformers;
    private ApiController $controller;

    protected function setUp(): void
    {
        $this->formFactory = $this->createMock(FormFactoryInterface::class);
        $this->transformers = new VersionTransformerCollection([]);
        $this->controller = new ApiController($this->formFactory, $this->transformers);
    }

    // =======================================================================
    // MISSING CONFIGURATION TESTS
    // =======================================================================

    public function testReturnsBadRequestWhenNoCommandClass(): void
    {
        $request = Request::create('/api/v1/test', 'POST');
        $request->attributes->set('_cortex_form_type', 'App\\Form\\FooType');
        $request->attributes->set('_cortex_api_version', 1);

        $response = ($this->controller)($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Missing action configuration.', $data['error']);
    }

    public function testReturnsBadRequestWhenNoFormType(): void
    {
        $request = Request::create('/api/v1/test', 'POST');
        $request->attributes->set('_cortex_command', 'Domain\\Foo\\Command');
        $request->attributes->set('_cortex_api_version', 1);

        $response = ($this->controller)($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    // =======================================================================
    // JSON PARSING TESTS
    // =======================================================================

    public function testReturnsBadRequestOnInvalidJson(): void
    {
        $request = Request::create('/api/v1/test', 'POST', [], [], [], [], 'not-json{{{');
        $request->attributes->set('_cortex_command', 'Domain\\Foo\\Command');
        $request->attributes->set('_cortex_form_type', 'App\\Form\\FooType');
        $request->attributes->set('_cortex_api_version', 1);

        $response = ($this->controller)($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid JSON body.', $data['error']);
    }

    // =======================================================================
    // SUCCESSFUL RESPONSE TESTS
    // =======================================================================

    public function testReturnsOkOnValidSubmission(): void
    {
        $result = new \stdClass();
        $result->uuid = 'abc-123';
        $result->name = 'Test';

        $this->setupFormFactory(true, $result);

        $request = $this->createApiRequest(['name' => 'Test']);
        $response = ($this->controller)($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('abc-123', $data['uuid']);
        $this->assertSame('Test', $data['name']);
    }

    public function testMergesRouteParamsIntoData(): void
    {
        $submittedData = null;
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(new \stdClass());
        $form->expects($this->once())
            ->method('submit')
            ->willReturnCallback(function ($data) use ($form, &$submittedData) {
                $submittedData = $data;

                return $form;
            });

        $this->formFactory->method('create')->willReturn($form);

        $request = Request::create('/api/v1/test', 'POST', [], [], [], [], json_encode(['name' => 'Test']));
        $request->attributes->set('_cortex_command', 'Domain\\Foo\\Command');
        $request->attributes->set('_cortex_form_type', 'App\\Form\\FooType');
        $request->attributes->set('_cortex_api_version', 1);
        $request->attributes->set('_route_params', ['uuid' => 'abc-123', '_controller' => 'ignored']);

        ($this->controller)($request);

        $this->assertSame('abc-123', $submittedData['uuid']);
        $this->assertSame('Test', $submittedData['name']);
        $this->assertArrayNotHasKey('_controller', $submittedData);
    }

    // =======================================================================
    // VALIDATION ERROR TESTS
    // =======================================================================

    public function testReturnsValidationErrorsOnInvalidForm(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(false);

        $fieldOrigin = $this->createMock(FormInterface::class);
        $fieldOrigin->method('getName')->willReturn('email');

        $error = new FormError('This value is not valid.');
        $ref = new \ReflectionProperty(FormError::class, 'origin');
        $ref->setValue($error, $fieldOrigin);

        $errorIterator = new FormErrorIterator($form, [$error]);
        $form->method('getErrors')->willReturn($errorIterator);
        $this->formFactory->method('create')->willReturn($form);

        $request = $this->createApiRequest(['email' => 'bad']);
        $response = ($this->controller)($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Validation failed.', $data['error']);
        $this->assertArrayHasKey('email', $data['violations']);
    }

    // =======================================================================
    // DOMAIN EXCEPTION TESTS
    // =======================================================================

    public function testReturnsDomainErrorOn422(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('submit')->willThrowException(
            new TestDomainException('Account not found.', 'account')
        );
        $this->formFactory->method('create')->willReturn($form);

        $request = $this->createApiRequest(['uuid' => 'missing']);
        $response = ($this->controller)($request);

        $this->assertSame(422, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Account not found.', $data['error']);
        $this->assertSame('account', $data['domain']);
    }

    // =======================================================================
    // VERSION HEADERS TESTS
    // =======================================================================

    public function testResponseIncludesApiVersionHeader(): void
    {
        $this->setupFormFactory(true, new \stdClass());

        $request = $this->createApiRequest([], version: 3);
        $response = ($this->controller)($request);

        $this->assertSame('3', $response->headers->get('X-API-Version'));
    }

    public function testDeprecatedResponseIncludesDeprecationHeaders(): void
    {
        $this->setupFormFactory(true, new \stdClass());

        $request = $this->createApiRequest([], version: 2, deprecated: true, sunset: '2026-09-01');
        $response = ($this->controller)($request);

        $this->assertSame('true', $response->headers->get('Deprecation'));
        $this->assertNotNull($response->headers->get('Sunset'));
        $this->assertStringContainsString('2026', $response->headers->get('Sunset'));
    }

    public function testNonDeprecatedResponseHasNoDeprecationHeaders(): void
    {
        $this->setupFormFactory(true, new \stdClass());

        $request = $this->createApiRequest([]);
        $response = ($this->controller)($request);

        $this->assertNull($response->headers->get('Deprecation'));
        $this->assertNull($response->headers->get('Sunset'));
    }

    public function testDeprecatedWithoutSunsetOmitsSunsetHeader(): void
    {
        $this->setupFormFactory(true, new \stdClass());

        $request = $this->createApiRequest([], deprecated: true);
        $response = ($this->controller)($request);

        $this->assertSame('true', $response->headers->get('Deprecation'));
        $this->assertNull($response->headers->get('Sunset'));
    }

    public function testVersionHeaderPresentOnValidationError(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(false);
        $form->method('getErrors')->willReturn(new FormErrorIterator($form, []));
        $this->formFactory->method('create')->willReturn($form);

        $request = $this->createApiRequest([], version: 2);
        $response = ($this->controller)($request);

        $this->assertSame('2', $response->headers->get('X-API-Version'));
    }

    public function testVersionHeaderPresentOnDomainError(): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('submit')->willThrowException(
            new TestDomainException('Error', 'test')
        );
        $this->formFactory->method('create')->willReturn($form);

        $request = $this->createApiRequest([], version: 5);
        $response = ($this->controller)($request);

        $this->assertSame('5', $response->headers->get('X-API-Version'));
    }

    // =======================================================================
    // SERIALIZATION TESTS
    // =======================================================================

    public function testSerializesBackedEnum(): void
    {
        $result = new \stdClass();
        $result->status = TestStatus::Active;

        $this->setupFormFactory(true, $result);

        $request = $this->createApiRequest([]);
        $response = ($this->controller)($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('active', $data['status']);
    }

    public function testSerializesDateTimeInterface(): void
    {
        $result = new \stdClass();
        $result->createdAt = new \DateTimeImmutable('2026-03-08T12:00:00+00:00');

        $this->setupFormFactory(true, $result);

        $request = $this->createApiRequest([]);
        $response = ($this->controller)($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame('2026-03-08T12:00:00+00:00', $data['createdAt']);
    }

    public function testSerializesNestedObjects(): void
    {
        $inner = new \stdClass();
        $inner->id = 42;

        $result = new \stdClass();
        $result->child = $inner;

        $this->setupFormFactory(true, $result);

        $request = $this->createApiRequest([]);
        $response = ($this->controller)($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(42, $data['child']['id']);
    }

    public function testSerializesArrays(): void
    {
        $this->setupFormFactory(true, ['a' => 1, 'b' => 2]);

        $request = $this->createApiRequest([]);
        $response = ($this->controller)($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(['a' => 1, 'b' => 2], $data);
    }

    // =======================================================================
    // TRANSFORM PIPELINE TESTS
    // =======================================================================

    public function testTransformRequestIsAppliedBeforeSubmit(): void
    {
        $transformer = $this->createMock(VersionTransformerInterface::class);
        $transformer->method('getCommandClass')->willReturn('Domain\\Foo\\Command');
        $transformer->method('transformRequest')
            ->willReturn(['transformed' => true]);
        $transformer->method('transformResponse')
            ->willReturnArgument(0);

        $this->transformers = new VersionTransformerCollection([$transformer]);
        $this->controller = new ApiController($this->formFactory, $this->transformers);

        $submittedData = null;
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn(true);
        $form->method('getData')->willReturn(new \stdClass());
        $form->expects($this->once())
            ->method('submit')
            ->willReturnCallback(function ($data) use ($form, &$submittedData) {
                $submittedData = $data;

                return $form;
            });

        $this->formFactory->method('create')->willReturn($form);

        $request = $this->createApiRequest(['original' => true]);

        ($this->controller)($request);

        $this->assertSame(['transformed' => true], $submittedData);
    }

    // =======================================================================
    // HELPERS
    // =======================================================================

    private function createApiRequest(
        array $body = [],
        int $version = 1,
        bool $deprecated = false,
        ?string $sunset = null,
    ): Request {
        $content = !empty($body) ? json_encode($body) : '';
        $request = Request::create('/api/v1/test', 'POST', [], [], [], [], $content);

        $request->attributes->set('_cortex_command', 'Domain\\Foo\\Command');
        $request->attributes->set('_cortex_form_type', 'App\\Form\\FooType');
        $request->attributes->set('_cortex_api_version', $version);
        $request->attributes->set('_cortex_deprecated', $deprecated);
        $request->attributes->set('_cortex_sunset', $sunset);

        return $request;
    }

    private function setupFormFactory(bool $isValid, mixed $data): void
    {
        $form = $this->createMock(FormInterface::class);
        $form->method('isValid')->willReturn($isValid);
        $form->method('getData')->willReturn($data);

        if (!$isValid) {
            $form->method('getErrors')->willReturn(new FormErrorIterator($form, []));
        }

        $this->formFactory->method('create')->willReturn($form);
    }
}

// Test helpers

namespace Cortex\Tests\Unit\Bridge\Symfony\Api;

use Cortex\Component\Exception\DomainException;

enum TestStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

class TestDomainException extends \RuntimeException implements DomainException
{
    public function __construct(string $message, private readonly string $domain)
    {
        parent::__construct($message);
    }

    public function getDomain(): string
    {
        return $this->domain;
    }
}
