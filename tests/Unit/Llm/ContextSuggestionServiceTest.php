<?php
namespace ArchitectureDiscovery\Tests\Unit\Llm;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use ArchitectureDiscovery\Llm\ArchitectureContextBuilder;
use ArchitectureDiscovery\Llm\ContextSuggestionService;
use ArchitectureDiscovery\Llm\LlmProviderInterface;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ContextSuggestionServiceTest extends TestCase
{
    public function testProviderReceivesNormalizedArchitectureOnly(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/secret/source', '1.0.0', new DateTimeImmutable()));
        $architecture->addClass(new ClassEntity('App\\Order', ClassEntity::TYPE_CLASS, 'App', 'Order', 'src/Order.php', 1));
        $architecture->setMetrics(['classCount' => 1, 'dependencyCount' => 0]);
        $architecture->setClusters([['id' => 'cluster-1', 'classes' => ['App\\Order'], 'metrics' => ['classCount' => 1]]]);

        $receivedContext = null;
        $provider = new class($receivedContext) implements LlmProviderInterface {
            public function __construct(private ?array &$receivedContext)
            {
            }

            public function name(): string
            {
                return 'test-local-provider';
            }

            public function suggest(array $normalizedContext): array
            {
                $this->receivedContext = $normalizedContext;
                return [[
                    'clusterId' => 'cluster-1',
                    'name' => 'Order Management',
                    'rationale' => 'The cluster contains order-related classes.',
                    'confidence' => 0.8,
                ]];
            }
        };

        $result = (new ContextSuggestionService(new ArchitectureContextBuilder()))->suggest($architecture, $provider);

        $this->assertArrayHasKey('detectedFacts', $result);
        $this->assertArrayHasKey('suggestedInterpretations', $result);
        $this->assertSame('test-local-provider', $result['provider']);
        $this->assertSame('1.0.0', $receivedContext['modelVersion']);
        $this->assertArrayNotHasKey('source', $receivedContext);
        $this->assertArrayNotHasKey('file', $receivedContext['classes'][0]);
        $this->assertSame('Order Management', $result['suggestedInterpretations'][0]['name']);
    }

    public function testContextIncludesStructuredModuleCandidateFactsWithoutProviderSpecificInterpretation(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/secret/source', '1.0.0', new DateTimeImmutable()));
        $architecture->setModuleCandidates([[
            'id' => 'module-todo',
            'name' => 'Todo',
            'coreMembers' => ['App\\Todo'],
            'relatedMembers' => ['App\\Shared\\Logger'],
            'supportingMembers' => [],
            'confidence' => 0.8,
            'evidence' => ['token' => 'todo', 'signals' => ['name' => ['App\\Todo']]],
            'namespace' => [
                'expectedPatterns' => ['App\\Todo'],
                'alignedMembers' => [],
                'driftingMembers' => [[
                    'class' => 'App\\Todo',
                    'namespace' => 'App',
                    'file' => 'src/Todo.php',
                    'role' => 'domain',
                    'confidence' => 0.5,
                    'evidence' => ['reason' => 'namespace_pattern_mismatch'],
                    'suggestedNamespace' => 'App\\Todo',
                ]],
            ],
        ]]);
        $architecture->setClassMemberships([
            'App\\Todo' => ['role' => 'domain', 'primaryCandidate' => 'module-todo', 'relatedCandidates' => [], 'supportingCandidates' => []],
            'App\\Legacy' => ['role' => 'unknown', 'primaryCandidate' => null, 'relatedCandidates' => [], 'supportingCandidates' => []],
        ]);
        $architecture->setUnassignedClasses(['App\\Legacy']);

        $context = (new ArchitectureContextBuilder())->build($architecture);

        $this->assertSame('module-todo', $context['moduleCandidates'][0]['id']);
        $this->assertSame('todo', $context['moduleCandidates'][0]['evidence']['token']);
        $this->assertSame('module-todo', $context['classMemberships']['App\\Todo']['primaryCandidate']);
        $this->assertSame(['App\\Legacy'], $context['unassignedClasses']);
        $this->assertArrayHasKey('namespace', $context['moduleCandidates'][0]);
        $this->assertArrayNotHasKey('boundedContexts', $context);
    }
}
