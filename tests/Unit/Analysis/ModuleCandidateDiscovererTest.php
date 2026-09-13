<?php
namespace ArchitectureDiscovery\Tests\Unit\Analysis;

use ArchitectureDiscovery\Analysis\ModuleCandidateDiscoverer;
use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
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
}