<?php
namespace ArchitectureDiscovery\Tests\Unit\Infrastructure\Analyzer;

use ArchitectureDiscovery\Infrastructure\Analyzer\Framework;
use ArchitectureDiscovery\Infrastructure\Analyzer\FrameworkPatternDetector;
use ArchitectureDiscovery\Infrastructure\Analyzer\LaravelAnalyzer;
use PHPUnit\Framework\TestCase;

final class LaravelAnalyzerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/laravel-analyzer-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
    }

    public function testIdentifiesItselfAsLaravelDetector(): void
    {
        $analyzer = new LaravelAnalyzer();

        $this->assertInstanceOf(FrameworkPatternDetector::class, $analyzer);
        $this->assertSame(Framework::Laravel, $analyzer->framework());
        $this->assertSame('laravel/framework', $analyzer->framework()->composerPackage());
    }

    public function testExtractsOrmAndDynamicRelationships(): void
    {
        $file = $this->tempDir . '/Order.php';
        file_put_contents($file, <<<'PHP'
<?php
namespace App\Models;

class Order
{
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function lineItems()
    {
        return $this->hasMany('App\Models\LineItem');
    }

    public function boot(): void
    {
        app(AuditLogger::class);
        resolve('App\Services\Users');
    }
}
PHP
        );

        $analyzer = new LaravelAnalyzer();
        $relationships = $analyzer->analyzeFile($file);

        $this->assertCount(4, $relationships);
        $this->assertSame('App\Models\Order', $relationships[0]->from);
        $this->assertSame('Customer', $relationships[0]->target);
        $this->assertSame('orm_relation', $relationships[0]->type);
        $this->assertSame(3, $relationships[0]->weight);
        $this->assertSame('belongsTo', $relationships[0]->metadata['relation']);
        $this->assertTrue($relationships[0]->metadata['static']);

        $this->assertSame('App\Models\LineItem', $relationships[1]->target);

        $this->assertSame('dynamic_call', $relationships[2]->type);
        $this->assertSame('AuditLogger', $relationships[2]->target);
        $this->assertSame('app', $relationships[2]->metadata['method']);
        $this->assertSame(2, $relationships[2]->weight);

        $this->assertSame('App\Services\Users', $relationships[3]->target);
        $this->assertSame('resolve', $relationships[3]->metadata['method']);
    }

    public function testIgnoresNonLaravelMethodsAndReportsUnresolvedDynamicCalls(): void
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
        resolve($this->serviceName);
    }
}
PHP
        );

        $analyzer = new LaravelAnalyzer();
        $relationships = $analyzer->analyzeFile($file);

        $this->assertCount(1, $relationships);
        $this->assertSame('dynamic_call', $relationships[0]->type);
        $this->assertFalse($relationships[0]->metadata['static']);
        $this->assertNull($relationships[0]->target);
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
