<?php
namespace ArchitectureDiscovery\Tests\Unit\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use ArchitectureDiscovery\Reporting\ModuleOverviewRenderer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ModuleOverviewRendererTest extends TestCase
{
    public function testRendersCandidateGroupsSharedCandidateAggregatesAndAdjacentUnassignedCode(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/tmp/demo', '1.0.0', new DateTimeImmutable()));
        $order = new ClassEntity('App\\Order\\Order', 'class', 'App\\Order', 'Order', 'src/Order/Order.php', 1);
        $user = new ClassEntity('App\\User\\User', 'class', 'App\\User', 'User', 'src/User/User.php', 1);
        $billing = new ClassEntity('App\\Billing\\Billing', 'class', 'App\\Billing', 'Billing', 'src/Billing/Billing.php', 1);
        $legacy = new ClassEntity('App\\Legacy\\LegacyOrder', 'class', 'App\\Legacy', 'LegacyOrder', 'src/Legacy/LegacyOrder.php', 1);
        foreach ([$order, $user, $billing, $legacy] as $class) {
            $architecture->addClass($class);
        }
        $architecture->addDependency(new Dependency($order, $user, Dependency::TYPE_USES, 3));
        $architecture->addDependency(new Dependency($order, $user, Dependency::TYPE_METHOD_CALL, 2));
        $architecture->addDependency(new Dependency($order, $legacy, Dependency::TYPE_USES, 1));
        $architecture->addDependency(new Dependency($billing, $user, Dependency::TYPE_USES, 1));
        $architecture->setModuleCandidates([
            [
                'id' => 'module-order',
                'name' => 'Order',
                'coreMembers' => [$order->getFullyQualifiedName()],
                'relatedMembers' => [$user->getFullyQualifiedName()],
                'supportingMembers' => [],
                'entrypoints' => [],
                'confidence' => 0.9,
                'evidence' => [],
            ],
            [
                'id' => 'module-user',
                'name' => 'User',
                'coreMembers' => [$user->getFullyQualifiedName()],
                'relatedMembers' => [],
                'supportingMembers' => [],
                'entrypoints' => [],
                'confidence' => 0.7,
                'evidence' => [],
            ],
            [
                'id' => 'module-billing',
                'name' => 'Billing',
                'coreMembers' => [$billing->getFullyQualifiedName()],
                'relatedMembers' => [$user->getFullyQualifiedName()],
                'supportingMembers' => [],
                'entrypoints' => [],
                'confidence' => 0.8,
                'evidence' => [],
            ],
        ]);
        $architecture->setClassMemberships([
            $order->getFullyQualifiedName() => ['primaryCandidate' => 'module-order'],
            $user->getFullyQualifiedName() => ['primaryCandidate' => 'module-user'],
            $billing->getFullyQualifiedName() => ['primaryCandidate' => 'module-billing'],
            $legacy->getFullyQualifiedName() => ['primaryCandidate' => null],
        ]);
        $architecture->setUnassignedClasses([$legacy->getFullyQualifiedName()]);

        $dot = (new ModuleOverviewRenderer())->renderDot($architecture);

        $this->assertStringContainsString('subgraph "cluster_module-order"', $dot);
        $this->assertStringContainsString('subgraph "cluster_module-user"', $dot);
        $this->assertSame(1, substr_count($dot, 'User (confidence 0.70, shared)'));
        $this->assertStringContainsString('confidence 0.90', $dot);
        $this->assertStringContainsString('count=2, strength=5', $dot);
        $this->assertStringContainsString('LegacyOrder', $dot);
        $this->assertStringContainsString('Legend', $dot);

        $svg = (new ModuleOverviewRenderer())->renderFallbackSvg($architecture);
        $this->assertStringContainsString('Namespace drift: dotted', $svg);
        $this->assertStringContainsString('Unassigned: dashed', $svg);
    }
}