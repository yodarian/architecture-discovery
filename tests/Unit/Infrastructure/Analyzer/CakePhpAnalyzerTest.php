<?php
namespace ArchitectureDiscovery\Tests\Unit\Infrastructure\Analyzer;

use ArchitectureDiscovery\Infrastructure\Analyzer\CakePhpAnalyzer;
use PHPUnit\Framework\TestCase;

final class CakePhpAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/cakephp-analyzer-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
    }

    public function testExtractsOrmAndDynamicRelationships(): void
    {
        $file = $this->tempDir . '/OrdersTable.php';
        file_put_contents($file, <<<'PHP'
<?php
namespace App\Model\Table;

class OrdersTable
{
    public function initialize(): void
    {
        $this->belongsTo('Customers');
        $this->hasMany('LineItems');
        $this->hasOne('Invoice');
        $this->belongsToMany('Tags');
        $this->fetchTable('AuditLogs');
        $this->loadModel('Users');
        $this->fetchTable($this->tableName);
    }
}
PHP
        );

        $analyzer = new CakePhpAnalyzer();
        $relationships = $analyzer->analyzeFile($file);

        $this->assertCount(7, $relationships);
        $this->assertSame('App\Model\Table\OrdersTable', $relationships[0]['from']);
        $this->assertSame('Customers', $relationships[0]['target']);
        $this->assertSame('orm_relation', $relationships[0]['type']);
        $this->assertSame(3, $relationships[0]['weight']);
        $this->assertSame('belongsTo', $relationships[0]['metadata']['relation']);
        $this->assertSame('fetchTable', $relationships[4]['metadata']['method']);
        $this->assertSame('dynamic_call', $relationships[4]['type']);
        $this->assertSame(2, $relationships[4]['weight']);
        $this->assertTrue($relationships[4]['metadata']['static']);
        $this->assertFalse($relationships[6]['metadata']['static']);
    }

    public function testIgnoresNonCakePhpMethodsAndReportsUnresolvedDynamicCalls(): void
    {
        $file = $this->tempDir . '/Service.php';
        file_put_contents($file, <<<'PHP'
<?php
namespace App;

class Service
{
    public function run(): void
    {
        $this->save('Users');
        $this->fetchTable();
    }
}
PHP
        );

        $analyzer = new CakePhpAnalyzer();
        $relationships = $analyzer->analyzeFile($file);

        $this->assertCount(1, $relationships);
        $this->assertSame('dynamic_call', $relationships[0]['type']);
        $this->assertFalse($relationships[0]['metadata']['static']);
        $this->assertNull($relationships[0]['target']);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getRealPath()) : unlink($file->getRealPath());
        }
        rmdir($directory);
    }
}
