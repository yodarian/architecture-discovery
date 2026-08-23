<?php
namespace ArchitectureDiscovery\Tests\Unit\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use ArchitectureDiscovery\Reporting\ArchitectureMapRenderer;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ArchitectureMapRendererTest extends TestCase
{
    public function testRendersClusterSummaryWithoutPerClassOrTerminologyContent(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/secret/source', '1.2.3', new DateTimeImmutable()));

        $ordersTable = new ClassEntity('App\\Model\\Table\\OrdersTable', ClassEntity::TYPE_CLASS, 'App\\Model\\Table', 'OrdersTable', 'src/Model/Table/OrdersTable.php', 1);
        $customersTable = new ClassEntity('App\\Model\\Table\\CustomersTable', ClassEntity::TYPE_CLASS, 'App\\Model\\Table', 'CustomersTable', 'src/Model/Table/CustomersTable.php', 1);
        $order = new ClassEntity('App\\Model\\Entity\\Order', ClassEntity::TYPE_CLASS, 'App\\Model\\Entity', 'Order', 'src/Model/Entity/Order.php', 1);
        $ordersController = new ClassEntity('App\\Controller\\OrdersController', ClassEntity::TYPE_CLASS, 'App\\Controller', 'OrdersController', 'src/Controller/OrdersController.php', 1);
        $helper = new ClassEntity('Vendor\\Lib\\Helper', ClassEntity::TYPE_CLASS, 'Vendor\\Lib', 'Helper', 'src/Vendor/Lib/Helper.php', 1);
        foreach ([$ordersTable, $customersTable, $order, $ordersController, $helper] as $class) {
            $architecture->addClass($class);
        }

        $architecture->addDependency(new Dependency($ordersTable, $customersTable, Dependency::TYPE_USES, 1));
        $architecture->addDependency(new Dependency(
            $ordersTable,
            $order,
            Dependency::TYPE_DYNAMIC_CALL,
            1,
            ['framework' => 'cakephp']
        ));
        $architecture->addDependency(new Dependency($ordersController, $helper, Dependency::TYPE_USES, 1));

        $architecture->setClusters([
            [
                'id' => 'cluster-1',
                'classes' => ['App\\Model\\Table\\OrdersTable', 'App\\Model\\Table\\CustomersTable'],
                'metrics' => ['classCount' => 2, 'internalEdges' => 1],
            ],
            [
                'id' => 'cluster-2',
                'classes' => ['App\\Model\\Entity\\Order'],
                'metrics' => ['classCount' => 1, 'internalEdges' => 0],
            ],
            [
                'id' => 'cluster-3',
                'classes' => ['App\\Controller\\OrdersController', 'Vendor\\Lib\\Helper'],
                'metrics' => ['classCount' => 2, 'internalEdges' => 1],
            ],
        ]);

        $markdown = (new ArchitectureMapRenderer())->render($architecture);

        $expected = <<<MARKDOWN
        # Architecture Map

        Project: demo
        Clusters: 3

        ## cluster-1 — App\\Model\\Table

        - Classes: 2
        - Internal dependencies: 1
        - Isolated: no
        - Framework-tagged relations: 1

        ## cluster-2 — App\\Model\\Entity

        - Classes: 1
        - Internal dependencies: 0
        - Isolated: yes
        - Framework-tagged relations: 1

        ## cluster-3 — mixed namespaces

        - Classes: 2
        - Internal dependencies: 1
        - Isolated: no
        - Framework-tagged relations: 0

        MARKDOWN;

        $this->assertSame($expected, $markdown);
        $this->assertStringNotContainsString('OrdersTable', $markdown);
        $this->assertStringNotContainsString('Bounded Context', $markdown);
        $this->assertStringNotContainsString('Incoming dependencies', $markdown);
        $this->assertStringNotContainsString('Outgoing dependencies', $markdown);
    }
}

