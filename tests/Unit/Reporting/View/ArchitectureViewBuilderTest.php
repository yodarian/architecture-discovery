<?php
namespace ArchitectureDiscovery\Tests\Unit\Reporting\View;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use ArchitectureDiscovery\Reporting\View\ArchitectureViewBuilder;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ArchitectureViewBuilderTest extends TestCase
{
    public function testBuildsNormalizedClassesDependenciesAndProjectInfo(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/secret/source', '1.2.3', new DateTimeImmutable()));
        $order = new ClassEntity('App\\Order', ClassEntity::TYPE_CLASS, 'App', 'Order', 'src/Order.php', 1);
        $customer = new ClassEntity('App\\Customer', ClassEntity::TYPE_CLASS, 'App', 'Customer', 'src/Customer.php', 1);
        $architecture->addClass($order);
        $architecture->addClass($customer);
        $architecture->addDependency(new Dependency($order, $customer, Dependency::TYPE_USES, 1));
        $architecture->setMetrics(['classCount' => 2, 'dependencyCount' => 1]);
        $architecture->setClusters([['id' => 'cluster-1', 'classes' => ['App\\Order', 'App\\Customer']]]);

        $view = (new ArchitectureViewBuilder())->build($architecture);

        $this->assertSame('1.2.3', $view->getModelVersion());
        $this->assertSame(['name' => 'demo', 'version' => '1.2.3'], $view->getProject());
        $this->assertSame(
            ['fqn' => 'App\\Customer', 'type' => 'class', 'namespace' => 'App', 'name' => 'Customer', 'abstract' => false],
            $view->getClasses()[0]
        );
        $this->assertArrayNotHasKey('file', $view->getClasses()[0]);
        $this->assertSame(
            ['from' => 'App\\Order', 'to' => 'App\\Customer', 'type' => Dependency::TYPE_USES, 'weight' => 1, 'metadata' => []],
            $view->getDependencies()[0]
        );
        $this->assertSame(['classCount' => 2, 'dependencyCount' => 1], $view->getMetrics());
        $this->assertSame([['id' => 'cluster-1', 'classes' => ['App\\Order', 'App\\Customer']]], $view->getClusters());
    }
}
