<?php
namespace ArchitectureDiscovery\Infrastructure\Scanner;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * FileScanner discovers PHP files in a project directory.
 * It handles filtering vendor, node_modules, and other common paths.
 */
final class FileScanner
{
    /** @var string[] */
    private array $excludeDirs = [
        'vendor',
        'node_modules',
        '.git',
        '.vendor',
        'build',
        'dist',
    ];

    /**
     * @param string[] $excludeDirs Directories to exclude from scanning
     */
    public function __construct(array $excludeDirs = [])
    {
        if (!empty($excludeDirs)) {
            $this->excludeDirs = array_values(array_unique(array_merge($this->excludeDirs, $excludeDirs)));
        }
    }

    /**
     * Scan a directory and return all PHP files.
     *
     * @return SplFileInfo[]
     */
    public function scanDirectory(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new \InvalidArgumentException("Directory does not exist: {$directory}");
        }

        $phpFiles = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            // Only include PHP files
            if ($file->isFile() && $file->getExtension() === 'php') {
                // Check if path contains excluded directories
                $relativePath = $this->getRelativePath($directory, $file->getPathname());
                if (!$this->isPathExcluded($relativePath)) {
                    $phpFiles[] = $file;
                }
            }
        }

        usort($phpFiles, fn(SplFileInfo $left, SplFileInfo $right) =>
            strcmp($this->getRelativePath($directory, $left->getPathname()), $this->getRelativePath($directory, $right->getPathname()))
        );

        return $phpFiles;
    }

    private function getRelativePath(string $basePath, string $filePath): string
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($filePath, $basePath) === 0) {
            return substr($filePath, strlen($basePath));
        }
        return $filePath;
    }

    private function isPathExcluded(string $path): bool
    {
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        foreach ($parts as $part) {
            if (in_array($part, $this->excludeDirs, true)) {
                return true;
            }
        }
        return false;
    }
}
