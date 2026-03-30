<?php

declare(strict_types=1);

namespace Cortex\Tests\Unit\Bridge\Symfony\Serializer;

use Cortex\Bridge\Symfony\Serializer\CortexNormalizer;
use Cortex\Bridge\Symfony\Serializer\Event\PreDenormalizeEvent;
use Cortex\Bridge\Symfony\Serializer\Event\PreNormalizeEvent;
use Cortex\Bridge\Symfony\Serializer\RepresentationRegistry;
use Cortex\Component\Mapper\ArrayMapper;
use Cortex\Component\Mapper\DefaultPublicGroupsTrait;
use Cortex\Component\Mapper\ModelRepresentation;
use Cortex\Component\Mapper\Strategy;
use Cortex\Component\Mapper\Value;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @covers \Cortex\Bridge\Symfony\Serializer\CortexNormalizer
 */
class CortexNormalizerTest extends TestCase
{
    private RepresentationRegistry $registry;
    private EventDispatcher $dispatcher;
    private CortexNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->registry = new RepresentationRegistry();
        $this->dispatcher = new EventDispatcher();
        $this->normalizer = new CortexNormalizer($this->registry, $this->dispatcher, new NullLogger());
    }

    // =======================================================================
    // SUPPORTS
    // =======================================================================

    public function testSupportsNormalizationForRegisteredModel(): void
    {
        $this->registerArticle();

        $article = $this->makeArticle();
        $this->assertTrue($this->normalizer->supportsNormalization($article));
    }

    public function testDoesNotSupportScalar(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization('string'));
    }

    public function testDoesNotSupportUnregisteredObject(): void
    {
        $this->assertFalse($this->normalizer->supportsNormalization(new \stdClass()));
    }

    public function testSupportsDenormalizationForRegisteredModel(): void
    {
        $this->registerArticle();

        $this->assertTrue($this->normalizer->supportsDenormalization([], Article::class));
    }

    // =======================================================================
    // NORMALIZE — NO GROUP (default, no filtering)
    // =======================================================================

    public function testNormalizeWithoutGroupReturnsAllFields(): void
    {
        $this->registerArticle();

        $result = $this->normalizer->normalize($this->makeArticle());

        $this->assertArrayHasKey('uuid', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertArrayHasKey('isActive', $result);
        $this->assertSame('art-1', $result['uuid']);
        $this->assertSame('Mon Article', $result['title']);
    }

    // =======================================================================
    // NORMALIZE — SIMPLE GROUP
    // =======================================================================

    public function testNormalizeWithIdGroup(): void
    {
        $this->registerArticle();

        $result = $this->normalizer->normalize(
            $this->makeArticle(),
            context: ['cortex_group' => 'id'],
        );

        $this->assertSame(['uuid' => 'art-1'], $result);
    }

    public function testNormalizeWithListGroup(): void
    {
        $this->registerArticle();

        $result = $this->normalizer->normalize(
            $this->makeArticle(),
            context: ['cortex_group' => 'list'],
        );

        $this->assertArrayHasKey('uuid', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayNotHasKey('body', $result);
    }

    // =======================================================================
    // NORMALIZE — @HERITAGE
    // =======================================================================

    public function testHeritageFlattensParentGroup(): void
    {
        $this->registerArticle();

        $result = $this->normalizer->normalize(
            $this->makeArticle(),
            context: ['cortex_group' => 'detail'],
        );

        // detail inherits from list (uuid, title, status) + adds body
        $this->assertArrayHasKey('uuid', $result);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('body', $result);
        $this->assertSame('Contenu complet', $result['body']);
    }

    // =======================================================================
    // NORMALIZE — ?OPTIONAL
    // =======================================================================

    public function testOptionalFieldOmittedWhenNull(): void
    {
        $this->registerArticle();

        $article = $this->makeArticle(excerpt: null);

        $result = $this->normalizer->normalize(
            $article,
            context: ['cortex_group' => 'list'],
        );

        $this->assertArrayNotHasKey('excerpt', $result);
    }

    public function testOptionalFieldIncludedWhenNotNull(): void
    {
        $this->registerArticle();

        $article = $this->makeArticle(excerpt: 'Un résumé');

        $result = $this->normalizer->normalize(
            $article,
            context: ['cortex_group' => 'list'],
        );

        $this->assertSame('Un résumé', $result['excerpt']);
    }

    // =======================================================================
    // NORMALIZE — field@group PROPAGATION
    // =======================================================================

    public function testPropagationNormalizesRelationWithSubGroup(): void
    {
        $this->registerArticle();
        $this->registerCategory();

        $category = new Category('cat-1', 'Jeux', '#C41E3A');
        $article = $this->makeArticle(category: $category);

        $result = $this->normalizer->normalize(
            $article,
            context: ['cortex_group' => 'with_category'],
        );

        $this->assertSame(['uuid' => 'cat-1'], $result['category']);
    }

    public function testPropagationWithListGroupExpandsRelation(): void
    {
        $this->registerArticle();
        $this->registerCategory();

        $category = new Category('cat-1', 'Jeux', '#C41E3A');
        $article = $this->makeArticle(category: $category);

        $result = $this->normalizer->normalize(
            $article,
            context: ['cortex_group' => 'with_category_expanded'],
        );

        $this->assertSame('cat-1', $result['category']['uuid']);
        $this->assertSame('Jeux', $result['category']['name']);
        $this->assertSame('#C41E3A', $result['category']['color']);
    }

    // =======================================================================
    // NORMALIZE — COLLECTION PROPAGATION
    // =======================================================================

    public function testPropagationOnCollection(): void
    {
        $this->registerArticle();
        $this->registerCategory();

        $tags = [
            new Category('t-1', 'Stratégie', '#000'),
            new Category('t-2', 'Famille', '#FFF'),
        ];
        $article = $this->makeArticle(tags: $tags);

        $result = $this->normalizer->normalize(
            $article,
            context: ['cortex_group' => 'with_tags'],
        );

        $this->assertCount(2, $result['tags']);
        $this->assertSame(['uuid' => 't-1'], $result['tags'][0]);
        $this->assertSame(['uuid' => 't-2'], $result['tags'][1]);
    }

    // =======================================================================
    // NORMALIZE — HERITAGE OVERRIDE (last wins)
    // =======================================================================

    public function testOverrideInheritedField(): void
    {
        $this->registerArticle();
        $this->registerCategory();

        $category = new Category('cat-1', 'Jeux', '#C41E3A');
        $article = $this->makeArticle(category: $category);

        // 'override_test' inherits from 'with_category' (category@id)
        // then overrides with category@list
        $result = $this->normalizer->normalize(
            $article,
            context: ['cortex_group' => 'override_test'],
        );

        // Last wins: category@list should expand, not category@id
        $this->assertArrayHasKey('name', $result['category']);
    }

    // =======================================================================
    // EVENT — PreNormalizeEvent
    // =======================================================================

    public function testEventIsDispatched(): void
    {
        $this->registerArticle();
        $dispatched = false;

        $this->dispatcher->addListener(PreNormalizeEvent::class, function (PreNormalizeEvent $event) use (&$dispatched) {
            $dispatched = true;
            $this->assertSame(Article::class, $event->modelClass);
            $this->assertSame('list', $event->getGroup());
        });

        $this->normalizer->normalize($this->makeArticle(), context: ['cortex_group' => 'list']);

        $this->assertTrue($dispatched);
    }

    public function testEventCanModifyGroup(): void
    {
        $this->registerArticle();

        $this->dispatcher->addListener(PreNormalizeEvent::class, function (PreNormalizeEvent $event) {
            $event->setGroup('id');
        });

        $result = $this->normalizer->normalize(
            $this->makeArticle(),
            context: ['cortex_group' => 'detail'],
        );

        $this->assertSame(['uuid' => 'art-1'], $result);
    }

    public function testStopPropagationPreventsSubsequentListeners(): void
    {
        $this->registerArticle();

        // First listener (high prio) — security downgrade + stop
        $this->dispatcher->addListener(PreNormalizeEvent::class, function (PreNormalizeEvent $event) {
            $event->setGroup('id');
            $event->stopPropagation();
        }, priority: 10);

        // Second listener (low prio) — should NOT run
        $secondCalled = false;
        $this->dispatcher->addListener(PreNormalizeEvent::class, function () use (&$secondCalled) {
            $secondCalled = true;
        }, priority: 0);

        $result = $this->normalizer->normalize(
            $this->makeArticle(),
            context: ['cortex_group' => 'detail'],
        );

        $this->assertSame(['uuid' => 'art-1'], $result);
        $this->assertFalse($secondCalled);
    }

    public function testNoListenerLeavesGroupUnchanged(): void
    {
        $this->registerArticle();

        $result = $this->normalizer->normalize(
            $this->makeArticle(),
            context: ['cortex_group' => 'id'],
        );

        $this->assertSame(['uuid' => 'art-1'], $result);
    }

    // =======================================================================
    // DENORMALIZE
    // =======================================================================

    public function testDenormalize(): void
    {
        $this->registerArticle();

        $result = $this->normalizer->denormalize(
            ['uuid' => 'art-1', 'title' => 'Test', 'status' => 'draft'],
            Article::class,
        );

        $this->assertSame('art-1', $result['uuid']);
        $this->assertSame('Test', $result['title']);
    }

    public function testDenormalizeDispatchesEvent(): void
    {
        $this->registerArticle();
        $dispatched = false;

        $this->dispatcher->addListener(PreDenormalizeEvent::class, function (PreDenormalizeEvent $event) use (&$dispatched) {
            $dispatched = true;
            $this->assertSame(Article::class, $event->modelClass);
            $this->assertSame('default', $event->getGroup());
            $this->assertSame('art-1', $event->data['uuid']);
        });

        $this->normalizer->denormalize(['uuid' => 'art-1'], Article::class);

        $this->assertTrue($dispatched);
    }

    public function testDenormalizeEventCanModifyGroup(): void
    {
        $this->registerArticle();

        $this->dispatcher->addListener(PreDenormalizeEvent::class, function (PreDenormalizeEvent $event) {
            $event->setGroup('store');
        });

        // store reader and default reader may return different results
        // (both exist in the fixture). The point is the event switches the group.
        $result = $this->normalizer->denormalize(
            ['uuid' => 'art-1', 'title' => 'Test'],
            Article::class,
            context: ['cortex_group' => 'default'],
        );

        $this->assertSame('art-1', $result['uuid']);
    }

    public function testDenormalizeStopPropagation(): void
    {
        $this->registerArticle();

        $this->dispatcher->addListener(PreDenormalizeEvent::class, function (PreDenormalizeEvent $event) {
            $event->setGroup('store');
            $event->stopPropagation();
        }, priority: 10);

        $secondCalled = false;
        $this->dispatcher->addListener(PreDenormalizeEvent::class, function () use (&$secondCalled) {
            $secondCalled = true;
        }, priority: 0);

        $this->normalizer->denormalize(['uuid' => 'art-1'], Article::class);

        $this->assertFalse($secondCalled);
    }

    // =======================================================================
    // HELPERS — fixtures
    // =======================================================================

    private function makeArticle(
        ?string $excerpt = 'Résumé par défaut',
        ?Category $category = null,
        array $tags = [],
    ): Article {
        return new Article(
            uuid: 'art-1',
            title: 'Mon Article',
            status: 'published',
            body: 'Contenu complet',
            isActive: true,
            excerpt: $excerpt,
            category: $category,
            tags: $tags,
        );
    }

    private function registerArticle(): void
    {
        $representation = new class implements ModelRepresentation {
            use DefaultPublicGroupsTrait;

            public function writer(string $group = 'default'): ArrayMapper
            {
                return new ArrayMapper(
                    mapping: ['isActive' => Value::Bool],
                    format: Strategy::AutoMapCamel,
                );
            }

            public function reader(string $group = 'default'): ArrayMapper
            {
                return new ArrayMapper(format: Strategy::AutoMapCamel);
            }

            public function groups(): array
            {
                return [
                    'store' => ['uuid', 'title', 'status', 'body', 'isActive', 'excerpt'],
                    'id' => ['uuid'],
                    'list' => ['uuid', 'title', 'status', '?excerpt'],
                    'detail' => ['@list', 'body'],
                    'with_category' => ['uuid', 'title', 'category@id'],
                    'with_category_expanded' => ['uuid', 'title', 'category@list'],
                    'with_tags' => ['uuid', 'title', 'tags@id'],
                    'override_test' => ['@with_category', 'category@list'],
                ];
            }
        };

        $this->registry->register(Article::class, $representation);
    }

    private function registerCategory(): void
    {
        $representation = new class implements ModelRepresentation {
            use DefaultPublicGroupsTrait;

            public function writer(string $group = 'default'): ArrayMapper
            {
                return new ArrayMapper(format: Strategy::AutoMapCamel);
            }

            public function reader(string $group = 'default'): ArrayMapper
            {
                return new ArrayMapper(format: Strategy::AutoMapCamel);
            }

            public function groups(): array
            {
                return [
                    'store' => ['uuid', 'name', 'color'],
                    'id' => ['uuid'],
                    'list' => ['uuid', 'name', 'color'],
                ];
            }
        };

        $this->registry->register(Category::class, $representation);
    }
}

// =======================================================================
// TEST MODELS (minimal POPOs)
// =======================================================================

class Article
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $title,
        public readonly string $status,
        public readonly string $body,
        public readonly bool $isActive,
        public readonly ?string $excerpt = null,
        public readonly ?Category $category = null,
        public readonly array $tags = [],
    ) {
    }
}

class Category
{
    public function __construct(
        public readonly string $uuid,
        public readonly string $name,
        public readonly string $color,
    ) {
    }
}
