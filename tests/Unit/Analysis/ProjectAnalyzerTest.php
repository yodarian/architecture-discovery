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
