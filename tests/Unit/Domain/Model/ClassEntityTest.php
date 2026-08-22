<?php
namespace ArchitectureDiscovery\Tests\Unit\Domain\Model;

use PHPUnit\Framework\TestCase;
use ArchitectureDiscovery\Domain\Model\ClassEntity;

final class ClassEntityTest extends TestCase
{
    public function testCreateClass(): void
    {
        $class = new ClassEntity(
            'My\\Test\\Service',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'Service',
            'src/Service.php',
            10,
            [],
            [],
            null,
            false
        );

        $this->assertSame('My\\Test\\Service', $class->getFullyQualifiedName());
        $this->assertSame(ClassEntity::TYPE_CLASS, $class->getType());
        $this->assertSame('My\\Test', $class->getNamespace());
        $this->assertSame('Service', $class->getName());
        $this->assertSame('src/Service.php', $class->getFile());
        $this->assertSame(10, $class->getLine());
        $this->assertEmpty($class->getInterfaces());
        $this->assertEmpty($class->getTraits());
        $this->assertNull($class->getExtends());
        $this->assertFalse($class->isAbstract());
    }

    public function testCreateInterface(): void
    {
        $interface = new ClassEntity(
            'My\\Test\\Repository',
            ClassEntity::TYPE_INTERFACE,
            'My\\Test',
            'Repository',
            'src/Repository.php',
            5
        );

        $this->assertSame(ClassEntity::TYPE_INTERFACE, $interface->getType());
    }

    public function testCreateTrait(): void
    {
        $trait = new ClassEntity(
            'My\\Test\\TimestampTrait',
            ClassEntity::TYPE_TRAIT,
            'My\\Test',
            'TimestampTrait',
            'src/TimestampTrait.php',
            3
        );

        $this->assertSame(ClassEntity::TYPE_TRAIT, $trait->getType());
    }

    public function testClassWithInheritance(): void
    {
        $class = new ClassEntity(
            'My\\Test\\UserService',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'UserService',
            'src/UserService.php',
            10,
            ['My\\Test\\BaseService'],
            [],
            'My\\Test\\AbstractService'
        );

        $this->assertSame('My\\Test\\AbstractService', $class->getExtends());
        $this->assertContains('My\\Test\\BaseService', $class->getInterfaces());
    }

    public function testClassWithTraits(): void
    {
        $class = new ClassEntity(
            'My\\Test\\Entity',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'Entity',
            'src/Entity.php',
            10,
            [],
            ['My\\Test\\Timestamps', 'My\\Test\\SoftDelete']
        );

        $this->assertCount(2, $class->getTraits());
        $this->assertContains('My\\Test\\Timestamps', $class->getTraits());
        $this->assertContains('My\\Test\\SoftDelete', $class->getTraits());
    }

    public function testAbstractClass(): void
    {
        $class = new ClassEntity(
            'My\\Test\\AbstractRepository',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'AbstractRepository',
            'src/AbstractRepository.php',
            5,
            [],
            [],
            null,
            true
        );

        $this->assertTrue($class->isAbstract());
    }

    public function testSerializeToArray(): void
    {
        $class = new ClassEntity(
            'My\\Test\\Service',
            ClassEntity::TYPE_CLASS,
            'My\\Test',
            'Service',
            'src/Service.php',
            10,
            ['My\\Test\\ServiceInterface'],
            ['My\\Test\\Logging'],
            'My\\Test\\BaseService',
            false
        );

        $array = $class->toArray();

        $this->assertSame('My\\Test\\Service', $array['fqn']);
        $this->assertSame('class', $array['type']);
        $this->assertSame('My\\Test', $array['namespace']);
        $this->assertSame('Service', $array['name']);
        $this->assertSame('src/Service.php', $array['file']);
        $this->assertSame(10, $array['line']);
        $this->assertFalse($array['abstract']);
        $this->assertSame('My\\Test\\BaseService', $array['extends']);
        $this->assertContains('My\\Test\\ServiceInterface', $array['implements']);
        $this->assertContains('My\\Test\\Logging', $array['uses']);
    }

    public function testSerializeMinimalArray(): void
    {
        $class = new ClassEntity(
            'SimpleClass',
            ClassEntity::TYPE_CLASS,
            '',
            'SimpleClass',
            'SimpleClass.php',
            1
        );

        $array = $class->toArray();

        $this->assertArrayHasKey('fqn', $array);
        $this->assertArrayHasKey('type', $array);
        $this->assertArrayHasKey('namespace', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('file', $array);
        $this->assertArrayHasKey('line', $array);
        $this->assertArrayHasKey('abstract', $array);
        // Optional fields should not be present if not set
        $this->assertArrayNotHasKey('extends', $array);
        $this->assertArrayNotHasKey('implements', $array);
        $this->assertArrayNotHasKey('uses', $array);
    }
}
