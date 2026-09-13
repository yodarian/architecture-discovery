<?php
namespace ArchitectureDiscovery\Tests\Unit\Analysis;

use ArchitectureDiscovery\Analysis\ModuleCandidateDiscoverer;
use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ModuleCandidateDiscovererTest extends TestCase
{
    public function testDiscoversTodoCandidateFromNamesNamespacesAndPaths(): void
    {
        $architecture = new Architecture(new ProjectMetadata(
            'todo-demo',
            '/tmp/todo-demo',
            '1.0.0',
            new DateTimeImmutable('2026-01-01T00:00:00Z')
        ));
        $classes = [
            new ClassEntity('App\\Application\\Todo\\TodoService', 'class', 'App\\Application\\Todo', 'TodoService', 'src/Application/Todo/TodoService.php', 1),
            new ClassEntity('App\\Domain\\Todo\\Todo', 'class', 'App\\Domain\\Todo', 'Todo', 'src/Domain/Todo/Todo.php', 1),
            new ClassEntity('App\\Interface\\Http\\TodoController', 'class', 'App\\Interface\\Http', 'TodoController', 'src/Interface/Http/TodoController.php', 1),
            new ClassEntity('App\\Infrastructure\\Persistence\\TodoRepository', 'class', 'App\\Infrastructure\\Persistence', 'TodoRepository', 'src/Infrastructure/Persistence/TodoRepository.php', 1),
            new ClassEntity('App\\Shared\\Clock', 'class', 'App\\Shared', 'Clock', 'src/Shared/Clock.php', 1),
        ];
        foreach ($classes as $class) {
            $architecture->addClass($class);
        }

        $candidates = (new ModuleCandidateDiscoverer())->discover($architecture);

        $this->assertCount(1, $candidates);
        $this->assertSame('module-todo', $candidates[0]['id']);
        $this->assertSame('Todo', $candidates[0]['name']);
        $this->assertSame([
            'App\\Application\\Todo\\TodoService',
            'App\\Domain\\Todo\\Todo',
            'App\\Infrastructure\\Persistence\\TodoRepository',
            'App\\Interface\\Http\\TodoController',
        ], $candidates[0]['coreMembers']);
        $this->assertSame(['App\\Interface\\Http\\TodoController'], $candidates[0]['entrypoints']);
        $this->assertSame(0.9, $candidates[0]['confidence']);
        $this->assertSame(['name', 'namespace', 'path'], array_keys($candidates[0]['evidence']['signals']));
        $this->assertSame(['App\\Shared\\Clock'], $architecture->getUnassignedClasses());
    }

    public function testNormalizesNamingStylesAtBoundaries(): void
    {
        $discoverer = new ModuleCandidateDiscoverer();

        $this->assertSame(
            ['todo', 'item', 'repository'],
            $discoverer->normalizeTokens('TodoItemRepository todo_item-repository')
        );
        $this->assertNotContains('todo', $discoverer->normalizeTokens('AntidoteService'));
    }

    public function testReportsSharedRelatedSupportingAndUnassignedCodeWithRoles(): void
    {
        $architecture = new Architecture(new ProjectMetadata(
            'multi-feature-demo',
            '/tmp/multi-feature-demo',
            '1.0.0',
            new DateTimeImmutable('2026-01-01T00:00:00Z')
        ));
        $classes = [
            new ClassEntity('App\\Order\\Order', 'class', 'App\\Order', 'Order', 'src/Order/Order.php', 1),
            new ClassEntity('App\\Order\\OrderService', 'class', 'App\\Order', 'OrderService', 'src/Order/OrderService.php', 1),
            new ClassEntity('App\\User\\User', 'class', 'App\\User', 'User', 'src/User/User.php', 1),
            new ClassEntity('App\\User\\UserService', 'class', 'App\\User', 'UserService', 'src/User/UserService.php', 1),
            new ClassEntity('App\\Infrastructure\\Audit\\AuditLogger', 'class', 'App\\Infrastructure\\Audit', 'AuditLogger', 'src/Infrastructure/Audit/AuditLogger.php', 1),
            new ClassEntity('App\\Shared\\Logger', 'class', 'App\\Shared', 'Logger', 'src/Shared/Logger.php', 1),
            new ClassEntity('App\\Order\\OrderTest', 'class', 'App\\Order', 'OrderTest', 'tests/Order/OrderTest.php', 1),
            new ClassEntity('App\\Migrations\\CreateOrdersTable', 'class', 'App\\Migrations', 'CreateOrdersTable', 'migrations/CreateOrdersTable.php', 1),
            new ClassEntity('App\\Kernel', 'class', 'App', 'Kernel', 'src/Kernel.php', 1),
            new ClassEntity('App\\Legacy\\Invoice', 'class', 'App\\Legacy', 'Invoice', 'src/Legacy/Invoice.php', 1),
        ];
        foreach ($classes as $class) {
            $architecture->addClass($class);
        }
        $architecture->addDependency(new Dependency($classes[1], $classes[5], Dependency::TYPE_USES));
        $architecture->addDependency(new Dependency($classes[3], $classes[5], Dependency::TYPE_USES));
        $architecture->addDependency(new Dependency($classes[6], $classes[0], Dependency::TYPE_USES));
        $architecture->addDependency(new Dependency($classes[7], $classes[0], Dependency::TYPE_USES));

        $candidates = (new ModuleCandidateDiscoverer())->discover($architecture);
        $byId = array_column($candidates, null, 'id');

        $this->assertArrayHasKey('module-order', $byId);
        $this->assertArrayHasKey('module-user', $byId);
        $this->assertContains('App\\Shared\\Logger', $byId['module-order']['relatedMembers']);
        $this->assertContains('App\\Shared\\Logger', $byId['module-user']['relatedMembers']);
        $this->assertContains('App\\Order\\OrderTest', $byId['module-order']['supportingMembers']);
        $this->assertContains('App\\Migrations\\CreateOrdersTable', $byId['module-order']['supportingMembers']);
        $this->assertSame('domain', $byId['module-order']['roles']['App\\Order\\Order']);
        $this->assertSame('application', $byId['module-order']['roles']['App\\Order\\OrderService']);
        $this->assertSame('infrastructure', $architecture->getClassMemberships()['App\\Infrastructure\\Audit\\AuditLogger']['role']);
        $this->assertSame('test', $architecture->getClassMemberships()['App\\Order\\OrderTest']['role']);
        $this->assertSame('framework', $architecture->getClassMemberships()['App\\Kernel']['role']);
        $this->assertContains('App\\Legacy\\Invoice', $architecture->getUnassignedClasses());
    }

    public function testReportsNamespaceAlignmentDriftAndSuggestedLocation(): void
    {
        $architecture = new Architecture(new ProjectMetadata(
            'namespace-demo',
            '/tmp/namespace-demo',
            '1.0.0',
            new DateTimeImmutable('2026-01-01T00:00:00Z')
        ));
        $aligned = new ClassEntity(
            'App\\Domain\\Todo\\Todo',
            'class',
            'App\\Domain\\Todo',
            'Todo',
            'src/Domain/Todo/Todo.php',
            1
        );
        $drifting = new ClassEntity(
            'App\\Legacy\\TodoService',
            'class',
            'App\\Legacy',
            'TodoService',
            'src/Legacy/TodoService.php',
            1
        );
        $configured = new ClassEntity(
            'App\\Features\\Todo\\TodoController',
            'class',
            'App\\Features\\Todo',
            'TodoController',
            'src/Features/Todo/TodoController.php',
            1
        );
        foreach ([$aligned, $drifting, $configured] as $class) {
            $architecture->addClass($class);
        }

        $candidates = (new ModuleCandidateDiscoverer([
            'todo' => ['App\\Features\\Todo'],
        ]))->discover($architecture);
        $todo = array_values(array_filter($candidates, static fn(array $candidate): bool => $candidate['id'] === 'module-todo'))[0];

        $this->assertSame(['App\\Features\\Todo'], $todo['namespace']['expectedPatterns']);
        $this->assertSame(['App\\Features\\Todo\\TodoController'], $todo['namespace']['alignedMembers']);
        $this->assertSame([
            [
                'class' => 'App\\Domain\\Todo\\Todo',
                'namespace' => 'App\\Domain\\Todo',
                'file' => 'src/Domain/Todo/Todo.php',
                'role' => 'domain',
                'confidence' => 0.5,
                'evidence' => ['token' => 'todo', 'reason' => 'namespace_pattern_mismatch'],
                'suggestedNamespace' => 'App\\Features\\Todo',
            ],
            [
                'class' => 'App\\Legacy\\TodoService',
                'namespace' => 'App\\Legacy',
                'file' => 'src/Legacy/TodoService.php',
                'role' => 'application',
                'confidence' => 0.5,
                'evidence' => ['token' => 'todo', 'reason' => 'namespace_pattern_mismatch'],
                'suggestedNamespace' => 'App\\Features\\Todo',
            ],
        ], $todo['namespace']['driftingMembers']);
    }
}