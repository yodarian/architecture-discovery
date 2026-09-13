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
        $this->removeDirectory($this->tempDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($dir);
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
        $this->assertArrayHasKey('moduleCandidates', $data);
        $this->assertSame([], $data['moduleCandidates']);
        $this->assertArrayHasKey('classMemberships', $data);
        $this->assertArrayHasKey('analysisConfiguration', $data);
        $this->assertFileExists($this->tempDir . '/out/graph.dot');
        $this->assertFileExists($this->tempDir . '/out/graph.svg');
        $this->assertFileExists($this->tempDir . '/out/modules.svg');
        $this->assertFileExists($this->tempDir . '/out/index.html');
        $this->assertFileExists($this->tempDir . '/out/architecture-map.md');
        $this->assertStringContainsString('<svg', file_get_contents($this->tempDir . '/out/graph.svg'));
        $this->assertStringContainsString('Architecture Overview', file_get_contents($this->tempDir . '/out/index.html'));
        $this->assertStringContainsString('# Architecture Map', file_get_contents($this->tempDir . '/out/architecture-map.md'));
    }

    public function testAnalyseExcludesArchitectureMapWhenFormatOmitsIt(): void
    {
        file_put_contents($this->tempDir . '/src/Foo.php', <<<'PHP'
<?php
namespace App;
class Foo {}
PHP
        );

        $application = new Application();
        $application->add(new AnalyseCommand());
        $tester = new CommandTester($application->find('analyse'));

        $exitCode = $tester->execute([
            'path' => $this->tempDir,
            '--output' => $this->tempDir . '/out',
            '--format' => ['json'],
        ]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->tempDir . '/out/architecture.json');
        $this->assertFileDoesNotExist($this->tempDir . '/out/architecture-map.md');
    }

    public function testConfigurationAliasesAndCliOverridesAreSerializedWithProvenance(): void
    {
        file_put_contents($this->tempDir . '/composer.json', json_encode([
            'extra' => ['architecture-discovery' => ['vocabulary' => ['todo' => ['task']]]],
        ]));
        file_put_contents($this->tempDir . '/src/Task.php', "<?php namespace App; class Task {}\n");
        file_put_contents($this->tempDir . '/src/Todo.php', "<?php namespace App; class Todo {}\n");
        file_put_contents($this->tempDir . '/src/WorkItem.php', "<?php namespace App; class WorkItem {}\n");
        file_put_contents($this->tempDir . '/config.json', json_encode([
            'vocabulary' => ['todo' => ['work_item']],
            'namespacePatterns' => ['todo' => ['App\\Features\\Todo']],
        ]));

        $application = new Application();
        $application->add(new AnalyseCommand());
        $tester = new CommandTester($application->find('analyse'));
        $exitCode = $tester->execute([
            'path' => $this->tempDir,
            '--output' => $this->tempDir . '/out',
            '--config' => $this->tempDir . '/config.json',
            '--alias' => ['todo=task'],
            '--format' => ['json'],
        ]);

        $this->assertSame(0, $exitCode);
        $data = json_decode(file_get_contents($this->tempDir . '/out/architecture.json'), true);
        $this->assertSame(['task'], $data['analysisConfiguration']['vocabulary']['todo']);
        $this->assertSame(['App\\Features\\Todo'], $data['analysisConfiguration']['namespacePatterns']['todo']);
        $this->assertContains('cli', $data['analysisConfiguration']['sources']);
        $this->assertArrayHasKey('module-todo', array_column($data['moduleCandidates'], null, 'id'));
    }

    public function testAnalyseDefaultsOutputToRepoOutDirectoryWithoutTouchingAnalyzedProject(): void
    {
        file_put_contents($this->tempDir . '/src/Foo.php', <<<'PHP'
<?php
namespace App;
class Foo {}
PHP
        );

        $application = new Application();
        $application->add(new AnalyseCommand());
        $tester = new CommandTester($application->find('analyse'));

        $repoRoot = dirname(__DIR__, 4);
        $expectedOutputDir = $repoRoot . '/out/' . basename($this->tempDir);

        try {
            $exitCode = $tester->execute(['path' => $this->tempDir]);

            $this->assertSame(0, $exitCode);
            $this->assertFileExists($expectedOutputDir . '/architecture.json');
            $this->assertFileDoesNotExist($this->tempDir . '/architecture.json');
            $this->assertDirectoryDoesNotExist($this->tempDir . '/build');
        } finally {
            $this->removeDirectory($expectedOutputDir);
        }
    }
}
