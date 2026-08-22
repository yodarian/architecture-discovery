<?php
namespace ArchitectureDiscovery\Tests\Unit\Analysis;

use ArchitectureDiscovery\Analysis\ArchitectureMetricsCalculator;
use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ArchitectureMetricsCalculatorTest extends TestCase
{
    public function testCalculatesGraphAndClassMetrics(): void
    {
        $architecture = new Architecture(new ProjectMetadata('demo', '/tmp/demo', '1.0.0', new DateTimeImmutable()));
        $first = new ClassEntity('App\\First', ClassEntity::TYPE_CLASS, 'App', 'First', 'src/First.php', 1);
        $second = new ClassEntity('App\\Second', ClassEntity::TYPE_CLASS, 'App', 'Second', 'src/Second.php', 1);
        $third = new ClassEntity('App\\Third', ClassEntity::TYPE_CLASS, 'App', 'Third', 'src/Third.php', 1);
        foreach ([$first, $second, $third] as $class) {
            $architecture->addClass($class);
        }
        $architecture->addDependency(new Dependency($first, $second, Dependency::TYPE_USES));
        $architecture->addDependency(new Dependency($second, $third, Dependency::TYPE_ORM_RELATION, 3));

        $metrics = (new ArchitectureMetricsCalculator())->calculate($architecture);

        $this->assertSame(3, $metrics['classCount']);
        $this->assertSame(2, $metrics['dependencyCount']);
        $this->assertSame(1, $metrics['cakePhpDependencyCount']);
        $this->assertSame(1, $metrics['classes']['App\\Second']['incoming']);
        $this->assertSame(1, $metrics['classes']['App\\Second']['outgoing']);
        $this->assertSame(2, $metrics['classes']['App\\Second']['dependencyCount']);
    }
}
