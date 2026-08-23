<?php
namespace ArchitectureDiscovery\Infrastructure\Analyzer;

/**
 * Detects a single framework's ORM/dynamic-resolution patterns from a PHP file's AST.
 */
interface FrameworkPatternDetector
{
    /**
     * The framework this detector recognizes patterns for.
     */
    public function framework(): Framework;

    /**
     * @return DetectedRelationship[]
     */
    public function analyzeFile(string $filePath): array;
}
