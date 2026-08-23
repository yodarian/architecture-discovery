<?php
namespace ArchitectureDiscovery\Tests\Unit\Infrastructure\Analyzer;

use ArchitectureDiscovery\Infrastructure\Analyzer\CakePhpAnalyzer;
use ArchitectureDiscovery\Infrastructure\Analyzer\FrameworkDetectorRegistry;
use ArchitectureDiscovery\Infrastructure\Analyzer\LaravelAnalyzer;
use PHPUnit\Framework\TestCase;

final class FrameworkDetectorRegistryTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/framework-detector-registry-test-' . uniqid();
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

    private function writeComposerJson(array $require): void
    {
        file_put_contents(
            $this->tempDir . '/composer.json',
            json_encode(['require' => $require], JSON_PRETTY_PRINT)
        );
    }

    public function testSelectsOnlyCakePhpWhenComposerRequiresIt(): void
    {
        $this->writeComposerJson(['php' => '^8.1', 'cakephp/cakephp' => '^5.0']);

        $detectors = (new FrameworkDetectorRegistry())->detectorsFor($this->tempDir);

        $this->assertCount(1, $detectors);
        $this->assertInstanceOf(CakePhpAnalyzer::class, $detectors[0]);
    }

    public function testSelectsOnlyLaravelWhenComposerRequiresIt(): void
    {
        $this->writeComposerJson(['php' => '^8.1', 'laravel/framework' => '^11.0']);

        $detectors = (new FrameworkDetectorRegistry())->detectorsFor($this->tempDir);

        $this->assertCount(1, $detectors);
        $this->assertInstanceOf(LaravelAnalyzer::class, $detectors[0]);
    }

    public function testRunsAllDetectorsWhenComposerJsonIsAbsent(): void
    {
        $detectors = (new FrameworkDetectorRegistry())->detectorsFor($this->tempDir);

        $this->assertCount(2, $detectors);
    }

    public function testRunsAllDetectorsWhenComposerJsonIsInconclusive(): void
    {
        $this->writeComposerJson(['php' => '^8.1']);

        $detectors = (new FrameworkDetectorRegistry())->detectorsFor($this->tempDir);

        $this->assertCount(2, $detectors);
    }
}
