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
    public function testRendersClusterSummaryWithTopClasses(): void
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
                - Top classes:
                    - App\\Model\\Table\\CustomersTable
                    - App\\Model\\Table\\OrdersTable

        ## cluster-2 — App\\Model\\Entity

        - Classes: 1
        - Internal dependencies: 0
        - Isolated: yes
        - Framework-tagged relations: 1
                - Top classes:
                    - App\\Model\\Entity\\Order

        ## cluster-3 — mixed namespaces

        - Classes: 2
        - Internal dependencies: 1
        - Isolated: no
        - Framework-tagged relations: 0
                - Top classes:
                    - Vendor\\Lib\\Helper
                    - App\\Controller\\OrdersController

        MARKDOWN;

        $expected = str_replace("\n        - Top classes:", "\n- Top classes:", $expected);
        $expected = str_replace("\n            - ", "\n  - ", $expected);
        $this->assertSame($expected, $markdown);
        $this->assertStringNotContainsString('Bounded Context', $markdown);
    }

    public function testRanksTopClassesByIncomingThenTotalDegreeThenFqnAndCapsAtFive(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/secret/source', '1.2.3', new DateTimeImmutable()));
        $alpha = new ClassEntity('App\\Alpha', ClassEntity::TYPE_CLASS, 'App', 'Alpha', 'src/Alpha.php', 1);
        $beta = new ClassEntity('App\\Beta', ClassEntity::TYPE_CLASS, 'App', 'Beta', 'src/Beta.php', 1);
        $gamma = new ClassEntity('App\\Gamma', ClassEntity::TYPE_CLASS, 'App', 'Gamma', 'src/Gamma.php', 1);
        $delta = new ClassEntity('App\\Delta', ClassEntity::TYPE_CLASS, 'App', 'Delta', 'src/Delta.php', 1);
        $epsilon = new ClassEntity('App\\Epsilon', ClassEntity::TYPE_CLASS, 'App', 'Epsilon', 'src/Epsilon.php', 1);
        $zeta = new ClassEntity('App\\Zeta', ClassEntity::TYPE_CLASS, 'App', 'Zeta', 'src/Zeta.php', 1);
        $sourceOne = new ClassEntity('App\\SourceOne', ClassEntity::TYPE_CLASS, 'App', 'SourceOne', 'src/SourceOne.php', 1);
        $sourceTwo = new ClassEntity('App\\SourceTwo', ClassEntity::TYPE_CLASS, 'App', 'SourceTwo', 'src/SourceTwo.php', 1);
        $external = new ClassEntity('App\\External', ClassEntity::TYPE_CLASS, 'App', 'External', 'src/External.php', 1);
        foreach ([$alpha, $beta, $gamma, $delta, $epsilon, $zeta, $sourceOne, $sourceTwo, $external] as $class) {
            $architecture->addClass($class);
        }

        foreach ([$alpha, $beta] as $target) {
            $architecture->addDependency(new Dependency($sourceOne, $target, Dependency::TYPE_USES));
            $architecture->addDependency(new Dependency($sourceTwo, $target, Dependency::TYPE_USES));
        }
        $architecture->addDependency(new Dependency($beta, $external, Dependency::TYPE_USES));
        $architecture->addDependency(new Dependency($sourceOne, $gamma, Dependency::TYPE_USES));
        $architecture->addDependency(new Dependency($sourceOne, $delta, Dependency::TYPE_USES));

        $architecture->setClusters([[
            'id' => 'cluster-1',
            'classes' => [$epsilon->getFullyQualifiedName(), $zeta->getFullyQualifiedName(), $gamma->getFullyQualifiedName(), $alpha->getFullyQualifiedName(), $delta->getFullyQualifiedName(), $beta->getFullyQualifiedName()],
            'metrics' => ['classCount' => 6, 'internalEdges' => 0],
        ]]);

        $expected = <<<MARKDOWN
        # Architecture Map

        Project: demo
        Clusters: 1

        ## cluster-1 — App

        - Classes: 6
        - Internal dependencies: 0
        - Isolated: no
        - Framework-tagged relations: 0
                - Top classes:
                    - App\\Beta
                    - App\\Alpha
                    - App\\Delta
                    - App\\Gamma
                    - App\\Epsilon

        MARKDOWN;

        $expected = str_replace("\n        - Top classes:", "\n- Top classes:", $expected);
        $expected = str_replace("\n            - ", "\n  - ", $expected);
        $this->assertSame($expected, (new ArchitectureMapRenderer())->render($architecture));
    }
}

