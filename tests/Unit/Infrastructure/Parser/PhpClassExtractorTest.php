<?php
namespace ArchitectureDiscovery\Tests\Unit\Infrastructure\Parser;

use PHPUnit\Framework\TestCase;
use ArchitectureDiscovery\Infrastructure\Parser\PhpClassExtractor;
use ArchitectureDiscovery\Domain\Model\ClassEntity;

final class PhpClassExtractorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/php-class-extractor-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
    }

    public function testExtractSimpleClass(): void
    {
        $phpFile = $this->tempDir . '/Test.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
namespace My\Namespace;

class SimpleClass {}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(1, $classes);
        $class = $classes[0];
        $this->assertSame('My\Namespace\SimpleClass', $class->getFullyQualifiedName());
        $this->assertSame(ClassEntity::TYPE_CLASS, $class->getType());
        $this->assertSame('My\Namespace', $class->getNamespace());
        $this->assertSame('SimpleClass', $class->getName());
    }

    public function testExtractInterface(): void
    {
        $phpFile = $this->tempDir . '/Repository.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
namespace App\Repository;

interface UserRepository {}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(1, $classes);
        $this->assertSame(ClassEntity::TYPE_INTERFACE, $classes[0]->getType());
    }

    public function testExtractTrait(): void
    {
        $phpFile = $this->tempDir . '/Timestamps.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
namespace App;

trait Timestamps {}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(1, $classes);
        $this->assertSame(ClassEntity::TYPE_TRAIT, $classes[0]->getType());
    }

    public function testExtractClassWithInheritance(): void
    {
        $phpFile = $this->tempDir . '/UserService.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
namespace App\Service;

use App\Base\BaseService;

class UserService extends BaseService {}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(1, $classes);
        $class = $classes[0];
        $this->assertSame('App\Base\BaseService', $class->getExtends());
    }

    public function testExtractClassWithInterfaces(): void
    {
        $phpFile = $this->tempDir . '/UserRepository.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
namespace App\Repository;

interface Repository {}
interface Queryable {}

class UserRepository implements Repository, Queryable {}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(3, $classes);
        $userRepo = array_values(array_filter($classes, fn($c) => $c->getName() === 'UserRepository'))[0];
        $interfaces = $userRepo->getInterfaces();
        $this->assertCount(2, $interfaces);
        $this->assertContains('App\Repository\Repository', $interfaces);
        $this->assertContains('App\Repository\Queryable', $interfaces);
    }

    public function testExtractClassWithTraits(): void
    {
        $phpFile = $this->tempDir . '/User.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
namespace App\Model;

trait Timestamps {}

class User {
    use Timestamps;
}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(2, $classes);
        $user = array_values(array_filter($classes, fn($c) => $c->getName() === 'User'))[0];
        $traits = $user->getTraits();
        $this->assertContains('App\Model\Timestamps', $traits);
    }

    public function testExtractClassTypeDependencies(): void
    {
        $phpFile = $this->tempDir . '/OrderService.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
namespace App\Service;

use App\Model\Order;
use App\Repository\OrderRepository;

class OrderService
{
    private OrderRepository $repository;

    public function __construct(OrderRepository $repository)
    {
        $this->repository = $repository;
    }

    public function find(): Order
    {
        return new Order();
    }
}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(1, $classes);
        $dependencies = $classes[0]->getTypeDependencies();
        $this->assertContains('App\Model\Order', $dependencies);
        $this->assertContains('App\Repository\OrderRepository', $dependencies);
    }

    public function testExtractAbstractClass(): void
    {
        $phpFile = $this->tempDir . '/AbstractService.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
namespace App;

abstract class AbstractService {}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(1, $classes);
        $this->assertTrue($classes[0]->isAbstract());
    }

    public function testExtractMultipleClasses(): void
    {
        $phpFile = $this->tempDir . '/MultiClass.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
namespace App;

class ClassOne {}
class ClassTwo {}
interface ClassThree {}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(3, $classes);
    }

    public function testExtractClassWithoutNamespace(): void
    {
        $phpFile = $this->tempDir . '/GlobalClass.php';
        file_put_contents($phpFile, <<<'PHP'
<?php

class GlobalClass {}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        $classes = $extractor->extractFromFile($phpFile);

        $this->assertCount(1, $classes);
        $class = $classes[0];
        $this->assertSame('GlobalClass', $class->getFullyQualifiedName());
        $this->assertSame('', $class->getNamespace());
    }

    public function testExtractFromInvalidFile(): void
    {
        $extractor = new PhpClassExtractor($this->tempDir);
        $this->expectException(\InvalidArgumentException::class);
        $extractor->extractFromFile('/nonexistent/file.php');
    }

    public function testExtractFromMalformedPhp(): void
    {
        $phpFile = $this->tempDir . '/BadSyntax.php';
        file_put_contents($phpFile, <<<'PHP'
<?php
class Broken {
    this is invalid php
}
PHP
        );

        $extractor = new PhpClassExtractor($this->tempDir);
        // Should not throw, but return empty array on parse error
        $classes = $extractor->extractFromFile($phpFile);
        $this->assertEmpty($classes);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
