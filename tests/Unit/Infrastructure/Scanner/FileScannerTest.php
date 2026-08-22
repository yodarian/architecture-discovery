<?php
namespace ArchitectureDiscovery\Tests\Unit\Infrastructure\Scanner;

use PHPUnit\Framework\TestCase;
use ArchitectureDiscovery\Infrastructure\Scanner\FileScanner;

final class FileScannerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/architecture-discovery-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->tempDir);
    }

    public function testScanEmptyDirectory(): void
    {
        $scanner = new FileScanner();
        $files = $scanner->scanDirectory($this->tempDir);
        $this->assertEmpty($files);
    }

    public function testScanDirectoryWithPhpFiles(): void
    {
        $this->createFile('src/Class1.php', '<?php class Class1 {}');
        $this->createFile('src/Class2.php', '<?php class Class2 {}');
        $this->createFile('test/Test.php', '<?php class Test {}');

        $scanner = new FileScanner();
        $files = $scanner->scanDirectory($this->tempDir);

        $this->assertCount(3, $files);
    }

    public function testScanDirectoryExcludesVendor(): void
    {
        $this->createFile('src/Class1.php', '<?php class Class1 {}');
        $this->createFile('vendor/dep/Class.php', '<?php class Class {}');

        $scanner = new FileScanner();
        $files = $scanner->scanDirectory($this->tempDir);

        $this->assertCount(1, $files);
        $this->assertStringNotContainsString('vendor', $files[0]->getPathname());
    }

    public function testScanDirectoryExcludesMultiplePaths(): void
    {
        $this->createFile('src/Class1.php', '<?php class Class1 {}');
        $this->createFile('vendor/dep/Class.php', '<?php class Class {}');
        $this->createFile('node_modules/pkg/file.php', '<?php');
        $this->createFile('build/artifact.php', '<?php');

        $scanner = new FileScanner();
        $files = $scanner->scanDirectory($this->tempDir);

        $this->assertCount(1, $files);
        $phpFile = $files[0];
        $this->assertStringContainsString('src', $phpFile->getPathname());
    }

    public function testScanDirectoryWithCustomExclusions(): void
    {
        $this->createFile('src/Class1.php', '<?php class Class1 {}');
        $this->createFile('custom/Class.php', '<?php class Class {}');

        $scanner = new FileScanner(['custom']);
        $files = $scanner->scanDirectory($this->tempDir);

        $this->assertCount(1, $files);
        $this->assertStringContainsString('src', $files[0]->getPathname());
    }

    public function testScanDirectoryIgnoresNonPhpFiles(): void
    {
        $this->createFile('src/Class.php', '<?php class Class {}');
        $this->createFile('src/README.md', '# README');
        $this->createFile('src/config.json', '{}');

        $scanner = new FileScanner();
        $files = $scanner->scanDirectory($this->tempDir);

        $this->assertCount(1, $files);
        $this->assertSame('php', $files[0]->getExtension());
    }

    public function testScanNonexistentDirectory(): void
    {
        $scanner = new FileScanner();
        $this->expectException(\InvalidArgumentException::class);
        $scanner->scanDirectory('/nonexistent/path');
    }

    private function createFile(string $relativePath, string $content): void
    {
        $fullPath = $this->tempDir . DIRECTORY_SEPARATOR . $relativePath;
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($fullPath, $content);
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
