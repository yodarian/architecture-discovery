<?php
namespace ArchitectureDiscovery\Tests\Unit\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use ArchitectureDiscovery\Reporting\ModuleDetailsRenderer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ModuleDetailsRendererTest extends TestCase
{
    public function testBuildsCompleteCandidateDetailWithCategoriesEvidenceDriftAndDependencies(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/tmp/demo', '1.0.0', new DateTimeImmutable()));
        $core = new ClassEntity('App\\Legacy\\TodoService', 'class', 'App\\Legacy', 'TodoService', 'src/Legacy/TodoService.php', 1);
        $related = new ClassEntity('App\\Shared\\Logger', 'class', 'App\\Shared', 'Logger', 'src/Shared/Logger.php', 1);
        $unassigned = new ClassEntity('App\\Legacy\\LegacyTodo', 'class', 'App\\Legacy', 'LegacyTodo', 'src/Legacy/LegacyTodo.php', 1);
        foreach ([$core, $related, $unassigned] as $class) {
            $architecture->addClass($class);
        }
        $architecture->addDependency(new Dependency($core, $related, Dependency::TYPE_USES, 2));
        $architecture->addDependency(new Dependency($core, $unassigned, Dependency::TYPE_USES, 1));
        $architecture->setModuleCandidates([[
            'id' => 'module-todo',
            'name' => 'Todo',
            'coreMembers' => [$core->getFullyQualifiedName()],
            'relatedMembers' => [$related->getFullyQualifiedName()],
            'supportingMembers' => [],
            'entrypoints' => [],
            'confidence' => 0.8,
            'evidence' => ['token' => 'todo', 'signals' => ['name' => [$core->getFullyQualifiedName()]]],
            'namespace' => ['driftingMembers' => [[
                'class' => $core->getFullyQualifiedName(),
                'suggestedNamespace' => 'App\\Todo',
                'evidence' => ['reason' => 'namespace_pattern_mismatch'],
            ]]],
        ], [
            'id' => 'module-audit',
            'name' => 'Audit',
            'coreMembers' => [],
            'relatedMembers' => [$related->getFullyQualifiedName()],
            'supportingMembers' => [],
            'entrypoints' => [],
            'confidence' => 0.5,
            'evidence' => [],
            'namespace' => ['driftingMembers' => []],
        ]]);
        $architecture->setClassMemberships([
            $core->getFullyQualifiedName() => ['role' => 'application', 'primaryCandidate' => 'module-todo'],
            $related->getFullyQualifiedName() => ['role' => 'shared', 'primaryCandidate' => null, 'relatedCandidates' => ['module-todo']],
            $unassigned->getFullyQualifiedName() => ['role' => 'unknown', 'primaryCandidate' => null],
        ]);
        $architecture->setUnassignedClasses([$unassigned->getFullyQualifiedName()]);

        $details = (new ModuleDetailsRenderer())->build($architecture, 'module-todo');

        $this->assertSame('module-todo', $details['candidate']['id']);
        $this->assertSame([$core->getFullyQualifiedName()], array_column($details['core'], 'class'));
        $this->assertSame([$related->getFullyQualifiedName()], array_column($details['related'], 'class'));
        $this->assertSame([$related->getFullyQualifiedName()], array_column($details['shared'], 'class'));
        $this->assertSame([$unassigned->getFullyQualifiedName()], array_column($details['unassigned'], 'class'));
        $this->assertSame('src/Legacy/TodoService.php', $details['core'][0]['file']);
        $this->assertSame('application', $details['core'][0]['role']);
        $this->assertSame(0.8, $details['core'][0]['confidence']);
        $this->assertSame('todo', $details['core'][0]['evidence']['token']);
        $this->assertTrue($details['core'][0]['namespaceAlignment']['drift']);
        $this->assertSame('App\\Todo', $details['core'][0]['namespaceAlignment']['suggestedNamespace']);
        $this->assertCount(2, $details['dependencies']);
        $this->assertStringContainsString('LegacyTodo', (new ModuleDetailsRenderer())->renderDot($architecture, 'Todo'));
    }

    public function testReturnsEmptyDetailForUnknownCandidate(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/tmp/demo', '1.0.0', new DateTimeImmutable()));

        $details = (new ModuleDetailsRenderer())->build($architecture, 'module-missing');

        $this->assertNull($details['candidate']);
        $this->assertSame([], $details['core']);
        $this->assertSame([], $details['dependencies']);
    }
}