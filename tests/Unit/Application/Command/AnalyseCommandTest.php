<?php
namespace ArchitectureDiscovery\Tests\Unit\Application\Command;

use ArchitectureDiscovery\Application\Command\AnalyseCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class AnalyseCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/analyse-command-test-' . uniqid();
        mkdir($this->tempDir . '/src', 0755, true);
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

    public function testAnalyseEmitsCakePhpDependencyEdges(): void
    {
        file_put_contents($this->tempDir . '/src/CustomersTable.php', <<<'PHP'
<?php
namespace App\Model\Table;
class CustomersTable {}
PHP
        );
        file_put_contents($this->tempDir . '/src/OrdersTable.php', <<<'PHP'
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

        $application = new Application();
        $application->add(new AnalyseCommand());
        $tester = new CommandTester($application->find('analyse'));

        $exitCode = $tester->execute([
            'path' => $this->tempDir,
            '--output' => $this->tempDir . '/out',
        ]);

        $this->assertSame(0, $exitCode);
        $data = json_decode(file_get_contents($this->tempDir . '/out/architecture.json'), true);
        $this->assertIsArray($data);
        $this->assertCount(2, $data['dependencies']);
        $dependenciesByType = [];
        foreach ($data['dependencies'] as $dependency) {
            $dependenciesByType[$dependency['type']] = $dependency;
        }
        $this->assertSame(3, $dependenciesByType['orm_relation']['weight']);
        $this->assertSame(2, $dependenciesByType['dynamic_call']['weight']);
        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('clusters', $data);
        $this->assertFileExists($this->tempDir . '/out/graph.dot');
        $this->assertFileExists($this->tempDir . '/out/graph.svg');
        $this->assertFileExists($this->tempDir . '/out/index.html');
        $this->assertStringContainsString('<svg', file_get_contents($this->tempDir . '/out/graph.svg'));
        $this->assertStringContainsString('Architecture Overview', file_get_contents($this->tempDir . '/out/index.html'));
    }
}
