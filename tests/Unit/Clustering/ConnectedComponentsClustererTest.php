<?php
namespace ArchitectureDiscovery\Tests\Unit\Clustering;

use ArchitectureDiscovery\Clustering\ConnectedComponentsClusterer;
use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ConnectedComponentsClustererTest extends TestCase
{
    public function testSuggestsClustersFromDependencyStructure(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/tmp/demo', '1.0.0', new DateTimeImmutable()));
        $classes = [];
        foreach (['A', 'B', 'C', 'D'] as $name) {
            $classes[$name] = new ClassEntity('App\\' . $name, ClassEntity::TYPE_CLASS, 'App', $name, 'src/' . $name . '.php', 1);
            $architecture->addClass($classes[$name]);
        }
        $architecture->addDependency(new Dependency($classes['A'], $classes['B'], Dependency::TYPE_USES));
        $architecture->addDependency(new Dependency($classes['B'], $classes['A'], Dependency::TYPE_USES));
        $architecture->addDependency(new Dependency($classes['C'], $classes['D'], Dependency::TYPE_USES));

        $clusters = (new ConnectedComponentsClusterer())->cluster($architecture);

        $this->assertCount(2, $clusters);
        $this->assertSame(['App\\A', 'App\\B'], $clusters[0]['classes']);
        $this->assertSame(['App\\C', 'App\\D'], $clusters[1]['classes']);
        $this->assertSame(2, $clusters[0]['metrics']['classCount']);
        $this->assertSame(2, $clusters[0]['metrics']['internalEdges']);
    }
}
