<?php
namespace ArchitectureDiscovery\Tests\Unit\Analysis;

use ArchitectureDiscovery\Analysis\ProjectAnalyzer;
use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ProjectAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/project-analyzer-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($this->tempDir);
    }

    private function newArchitecture(): Architecture
    {
        return new Architecture(new ProjectMetadata('demo', $this->tempDir, '1.0.0', new DateTimeImmutable()));
    }

    public function testBindsClassifiedTypeHintAndStaticCallDependencies(): void
    {
        file_put_contents($this->tempDir . '/Order.php', <<<'PHP'
<?php
class Order {}
PHP
        );
        file_put_contents($this->tempDir . '/OrderId.php', <<<'PHP'
<?php
class OrderId {}
PHP
        );
        file_put_contents($this->tempDir . '/OrderRepository.php', <<<'PHP'
<?php
class OrderRepository
{
    public static function assertValid(OrderId $id): void
    {
    }
}
PHP
        );
        file_put_contents($this->tempDir . '/Logger.php', <<<'PHP'
<?php
class Logger {}
PHP
        );
        file_put_contents($this->tempDir . '/OrderService.php', <<<'PHP'
<?php
class OrderService
{
    private OrderRepository $repository;

    public function find(OrderId $id): Order
    {
        OrderRepository::assertValid($id);
        new Logger();
        return new Order();
    }
}
PHP
        );

        $architecture = $this->newArchitecture();
        (new ProjectAnalyzer())->analyze($architecture, $this->tempDir);

        $dependenciesByType = [];
        foreach ($architecture->getDependencies() as $dependency) {
            $dependenciesByType[$dependency->getType()][] = $dependency;
        }

        $this->assertSame('OrderRepository', $dependenciesByType[Dependency::TYPE_PROPERTY_TYPE][0]->getTo()->getName());
        $this->assertSame('OrderId', $dependenciesByType[Dependency::TYPE_PARAMETER_TYPE][0]->getTo()->getName());
        $returnTypeTargets = array_map(
            static fn(Dependency $d) => $d->getTo()->getName(),
            $dependenciesByType[Dependency::TYPE_RETURN_TYPE]
        );
        $this->assertContains('Order', $returnTypeTargets);
        $this->assertSame('OrderRepository', $dependenciesByType[Dependency::TYPE_METHOD_CALL][0]->getTo()->getName());

        // `new Logger()` isn't a declared type hint or static call, so it's still generic TYPE_USES.
        // `Order` is fully explained by TYPE_RETURN_TYPE above, so it's excluded from TYPE_USES
        // even though it's also `new`-ed in the same method body.
        $usesTargets = array_map(
            static fn(Dependency $d) => $d->getTo()->getName(),
            $dependenciesByType[Dependency::TYPE_USES]
        );
        $this->assertContains('Logger', $usesTargets);
        $this->assertNotContains('Order', $usesTargets);
    }

    public function testBindsExtendsDependencyBetweenExtractedClasses(): void
    {
        file_put_contents($this->tempDir . '/Base.php', <<<'PHP'
<?php
class Base {}
PHP
        );
        file_put_contents($this->tempDir . '/Child.php', <<<'PHP'
<?php
class Child extends Base {}
PHP
        );

        $architecture = $this->newArchitecture();
        (new ProjectAnalyzer())->analyze($architecture, $this->tempDir);

        $this->assertCount(2, $architecture->getClasses());
        $dependencies = $architecture->getDependencies();
        $this->assertCount(1, $dependencies);
        $this->assertSame(Dependency::TYPE_EXTENDS, $dependencies[0]->getType());
        $this->assertSame('Child', $dependencies[0]->getFrom()->getName());
        $this->assertSame('Base', $dependencies[0]->getTo()->getName());
    }

    public function testResolvesCakePhpDynamicFetchTableToDiscoveredClass(): void
    {
        file_put_contents($this->tempDir . '/CustomersTable.php', <<<'PHP'
<?php
namespace App\Model\Table;
class CustomersTable {}
PHP
        );
        file_put_contents($this->tempDir . '/OrdersTable.php', <<<'PHP'
<?php
namespace App\Model\Table;
class OrdersTable
{
    public function initialize(): void
    {
        $this->belongsTo('Customers');
        $this->fetchTable('Customers');
    }
}
PHP
        );

        $architecture = $this->newArchitecture();
        (new ProjectAnalyzer())->analyze($architecture, $this->tempDir);

        $dependenciesByType = [];
        foreach ($architecture->getDependencies() as $dependency) {
            $dependenciesByType[$dependency->getType()] = $dependency;
        }
        $this->assertSame(3, $dependenciesByType[Dependency::TYPE_ORM_RELATION]->getWeight());
        $this->assertSame(2, $dependenciesByType[Dependency::TYPE_DYNAMIC_CALL]->getWeight());
        $this->assertSame('cakephp', $dependenciesByType[Dependency::TYPE_ORM_RELATION]->getMetadata()['framework']);
    }

    public function testResolvesLaravelEloquentRelationToDiscoveredClassWhenComposerRequiresLaravel(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'require' => ['laravel/framework' => '^11.0'],
        ]));
        file_put_contents($this->tempDir . '/Customer.php', <<<'PHP'
<?php
namespace App\Models;
class Customer {}
PHP
        );
        file_put_contents($this->tempDir . '/Order.php', <<<'PHP'
<?php
namespace App\Models;
class Order
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
PHP
        );

        $architecture = $this->newArchitecture();
        (new ProjectAnalyzer())->analyze($architecture, $this->tempDir);

        $dependenciesByType = [];
        foreach ($architecture->getDependencies() as $dependency) {
            $dependenciesByType[$dependency->getType()] = $dependency;
        }
        $ormDependency = $dependenciesByType[Dependency::TYPE_ORM_RELATION];
        $this->assertSame('Order', $ormDependency->getFrom()->getName());
        $this->assertSame('Customer', $ormDependency->getTo()->getName());
        $this->assertSame('laravel', $ormDependency->getMetadata()['framework']);
    }

    public function testReportsPerFileParseFailureViaProgressCallbackAndContinues(): void
    {
        file_put_contents($this->tempDir . '/Unreadable.php', <<<'PHP'
<?php
class Unreadable {}
PHP
        );
        chmod($this->tempDir . '/Unreadable.php', 0000);
        file_put_contents($this->tempDir . '/Good.php', <<<'PHP'
<?php
class Good {}
PHP
        );

        $messages = [];
        $architecture = $this->newArchitecture();
        // The permission-denied read triggers a PHP-level warning as well as our own progress message.
        @(new ProjectAnalyzer())->analyze(
            $architecture,
            $this->tempDir,
            [],
            function (string $message) use (&$messages): void {
                $messages[] = $message;
            }
        );
        chmod($this->tempDir . '/Unreadable.php', 0644);

        $this->assertCount(1, $architecture->getClasses());
        $this->assertSame('Good', $architecture->getClasses()[0]->getName());
        $this->assertNotEmpty(array_filter(
            $messages,
            static fn(string $message) => str_contains($message, 'Warning: Failed to parse Unreadable.php')
        ));
    }
}
