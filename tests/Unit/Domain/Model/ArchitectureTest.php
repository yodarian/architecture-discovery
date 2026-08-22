<?php
namespace ArchitectureDiscovery\Tests\Unit\Domain\Model;

use DateTime;
use PHPUnit\Framework\TestCase;
use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;

final class ArchitectureTest extends TestCase
{
    private ProjectMetadata $metadata;
    private Architecture $architecture;

    protected function setUp(): void
    {
        $this->metadata = new ProjectMetadata(
            'test-project',
            '/test/path',
            '1.0.0',
            new DateTime('2024-01-01T12:00:00Z')
        );
        $this->architecture = new Architecture($this->metadata);
    }

    public function testArchitectureInitialization(): void
    {
        $this->assertSame($this->metadata, $this->architecture->getMetadata());
        $this->assertEmpty($this->architecture->getClasses());
        $this->assertEmpty($this->architecture->getDependencies());
    }

    public function testAddClass(): void
    {
        $class = new ClassEntity(
            'My\\Test\\Class',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'Class',
            'src/Class.php',
            5
        );

        $this->architecture->addClass($class);
        $this->assertCount(1, $this->architecture->getClasses());
        $this->assertSame($class, $this->architecture->getClass('My\\Test\\Class'));
    }

    public function testAddMultipleClasses(): void
    {
        $class1 = new ClassEntity(
            'My\\Test\\Class1',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'Class1',
            'src/Class1.php',
            5
        );

        $class2 = new ClassEntity(
            'My\\Test\\Class2',
            ClassEntity::TYPE_INTERFACE,
            'My\\Test',
            'Class2',
            'src/Class2.php',
            10
        );

        $this->architecture->addClass($class1);
        $this->architecture->addClass($class2);

        $this->assertCount(2, $this->architecture->getClasses());
        $this->assertSame($class1, $this->architecture->getClass('My\\Test\\Class1'));
        $this->assertSame($class2, $this->architecture->getClass('My\\Test\\Class2'));
    }

    public function testAddDependency(): void
    {
        $from = new ClassEntity(
            'My\\Test\\Service',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'Service',
            'src/Service.php',
            5
        );

        $to = new ClassEntity(
            'My\\Test\\Repository',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'Repository',
            'src/Repository.php',
            10
        );

        $dependency = new Dependency(
            $from,
            $to,
            Dependency::TYPE_USES,
            1
        );

        $this->architecture->addClass($from);
        $this->architecture->addClass($to);
        $this->architecture->addDependency($dependency);

        $this->assertCount(1, $this->architecture->getDependencies());
        $dependencies = $this->architecture->getDependencies();
        $this->assertSame($dependency, $dependencies[0]);
    }

    public function testSerializeToArray(): void
    {
        $class = new ClassEntity(
            'My\\Test\\Class',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'Class',
            'src/Class.php',
            5
        );

        $this->architecture->addClass($class);

        $array = $this->architecture->toArray();

        $this->assertArrayHasKey('version', $array);
        $this->assertArrayHasKey('generatedAt', $array);
        $this->assertArrayHasKey('project', $array);
        $this->assertArrayHasKey('classes', $array);
        $this->assertArrayHasKey('dependencies', $array);

        $this->assertSame('1.0.0', $array['version']);
        $this->assertCount(1, $array['classes']);
        $this->assertCount(0, $array['dependencies']);
    }

    public function testSerializationOrdersClassesAndDependenciesDeterministically(): void
    {
        $first = new ClassEntity('App\First', ClassEntity::TYPE_CLASS, 'App', 'First', 'src/First.php', 1);
        $second = new ClassEntity('App\Second', ClassEntity::TYPE_CLASS, 'App', 'Second', 'src/Second.php', 1);

        $this->architecture->addClass($second);
        $this->architecture->addClass($first);
        $this->architecture->addDependency(new Dependency($second, $first, Dependency::TYPE_USES));
        $this->architecture->addDependency(new Dependency($first, $second, Dependency::TYPE_USES));

        $serialized = $this->architecture->toArray();

        $this->assertSame(['App\First', 'App\Second'], array_column($serialized['classes'], 'fqn'));
        $this->assertSame(
            ['App\First', 'App\Second'],
            array_column($serialized['dependencies'], 'from')
        );
    }
}
