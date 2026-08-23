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

        $orderService = new ClassEntity('App\\OrderService', ClassEntity::TYPE_CLASS, 'App', 'OrderService', 'src/OrderService.php', 1);
        $orderRepository = new ClassEntity('App\\OrderRepository', ClassEntity::TYPE_CLASS, 'App', 'OrderRepository', 'src/OrderRepository.php', 1);
        $mailer = new ClassEntity('App\\Mailer', ClassEntity::TYPE_CLASS, 'App', 'Mailer', 'src/Mailer.php', 1);
        $architecture->addClass($orderService);
        $architecture->addClass($orderRepository);
        $architecture->addClass($mailer);

        $architecture->addDependency(new Dependency($orderService, $orderRepository, Dependency::TYPE_USES, 1));
        $architecture->addDependency(new Dependency(
            $orderRepository,
            $mailer,
            Dependency::TYPE_DYNAMIC_CALL,
            1,
            ['framework' => 'cakephp']
        ));

        $architecture->setClusters([
            [
                'id' => 'cluster-1',
                'classes' => ['App\\OrderService', 'App\\OrderRepository'],
                'metrics' => ['classCount' => 2, 'internalEdges' => 1, 'incomingEdges' => 0, 'outgoingEdges' => 0],
            ],
            [
                'id' => 'cluster-2',
                'classes' => ['App\\Mailer'],
                'metrics' => ['classCount' => 1, 'internalEdges' => 0, 'incomingEdges' => 2, 'outgoingEdges' => 1],
            ],
        ]);

        $markdown = (new ArchitectureMapRenderer())->render($architecture);

        $expected = <<<MARKDOWN
        # Architecture Map

        Project: demo
        Clusters: 2

        ## cluster-1

        - Classes: 2
        - Internal dependencies: 1
        - Incoming dependencies (from other clusters): 0
        - Outgoing dependencies (to other clusters): 0
        - Framework-tagged relations: 1

        ## cluster-2

        - Classes: 1
        - Internal dependencies: 0
        - Incoming dependencies (from other clusters): 2
        - Outgoing dependencies (to other clusters): 1
        - Framework-tagged relations: 1

        MARKDOWN;

        $this->assertSame($expected, $markdown);
        $this->assertStringNotContainsString('OrderService', $markdown);
        $this->assertStringNotContainsString('Bounded Context', $markdown);
    }
}
