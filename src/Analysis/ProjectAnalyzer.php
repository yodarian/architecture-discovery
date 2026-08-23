<?php
namespace ArchitectureDiscovery\Analysis;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Infrastructure\Analyzer\CakePhpAnalyzer;
use ArchitectureDiscovery\Infrastructure\Parser\PhpClassExtractor;
use ArchitectureDiscovery\Infrastructure\Scanner\FileScanner;

/**
 * ProjectAnalyzer scans a project directory and binds the resulting classes
 * and dependencies onto an Architecture instance.
 */
final class ProjectAnalyzer
{
    /**
     * @param string[] $excludeDirs
     * @param ?callable(string): void $onProgress Called with human-readable progress messages
     */
    public function analyze(
        Architecture $architecture,
        string $projectPath,
        array $excludeDirs = [],
        ?callable $onProgress = null
    ): void {
        $onProgress ??= static function (string $message): void {
        };

        $onProgress('Scanning for PHP files...');
        $scanner = new FileScanner($excludeDirs);
        $files = $scanner->scanDirectory($projectPath);
        $onProgress('Found ' . count($files) . ' PHP files');

        $onProgress('Extracting classes from files...');
        $extractor = new PhpClassExtractor($projectPath);
        $cakePhpAnalyzer = new CakePhpAnalyzer();
        $cakeRelationships = [];
        $classCount = 0;

        foreach ($files as $file) {
            try {
                $classes = $extractor->extractFromFile($file->getRealPath());
                foreach ($classes as $class) {
                    $architecture->addClass($class);
                    $classCount++;
                }
                $cakeRelationships = array_merge(
                    $cakeRelationships,
                    $cakePhpAnalyzer->analyzeFile($file->getRealPath())
                );
            } catch (\Exception $e) {
                $onProgress("Warning: Failed to parse {$file->getFilename()}: {$e->getMessage()}");
            }
        }

        $this->addStructuralDependencies($architecture);
        $this->addCakePhpDependencies($architecture, $cakeRelationships);

        $onProgress("Extracted {$classCount} classes, interfaces, and traits");
    }

    private function addStructuralDependencies(Architecture $architecture): void
    {
        foreach ($architecture->getClasses() as $class) {
            if ($class->getExtends() !== null) {
                $target = $architecture->getClass($class->getExtends());
                if ($target !== null) {
                    $architecture->addDependency(new Dependency($class, $target, Dependency::TYPE_EXTENDS, 3));
                }
            }

            foreach ($class->getInterfaces() as $interfaceName) {
                $target = $architecture->getClass($interfaceName);
                if ($target !== null) {
                    $architecture->addDependency(new Dependency($class, $target, Dependency::TYPE_IMPLEMENTS, 2));
                }
            }

            foreach ($class->getTraits() as $traitName) {
                $target = $architecture->getClass($traitName);
                if ($target !== null) {
                    $architecture->addDependency(new Dependency($class, $target, Dependency::TYPE_TRAIT_USE, 2));
                }
            }

            $structuralDependencies = array_merge(
                $class->getInterfaces(),
                $class->getTraits(),
                $class->getExtends() !== null ? [$class->getExtends()] : []
            );
            foreach ($class->getTypeDependencies() as $typeName) {
                if (in_array($typeName, $structuralDependencies, true)) {
                    continue;
                }

                $target = $architecture->getClass($typeName);
                if ($target !== null && $target !== $class) {
                    $architecture->addDependency(new Dependency($class, $target, Dependency::TYPE_USES, 1));
                }
            }
        }
    }

    /**
     * Add CakePHP relationships when their target resolves to a discovered class.
     * Unresolved dynamic calls are intentionally omitted from graph edges but are
     * still detected by CakePhpAnalyzer for future reporting.
     *
     * @param array<int, array{from: string, target: string|null, type: string, weight: int, metadata: array<string, mixed>}> $relationships
     */
    private function addCakePhpDependencies(Architecture $architecture, array $relationships): void
    {
        foreach ($relationships as $relationship) {
            if ($relationship['target'] === null) {
                continue;
            }

            $from = $architecture->getClass($relationship['from']);
            $to = $this->resolveCakePhpTarget($architecture, $relationship['from'], $relationship['target']);
            if ($from === null || $to === null || $from === $to) {
                continue;
            }

            $metadata = $relationship['metadata'];
            $metadata['target'] = $relationship['target'];
            $architecture->addDependency(new Dependency(
                $from,
                $to,
                $relationship['type'],
                $relationship['weight'],
                $metadata
            ));
        }
    }

    private function resolveCakePhpTarget(Architecture $architecture, string $from, string $targetName): ?ClassEntity
    {
        $source = $architecture->getClass($from);
        if ($source === null) {
            return null;
        }

        $targetName = ltrim($targetName, '\\');
        $candidates = str_contains($targetName, '\\')
            ? [$targetName]
            : array_filter([
                $source->getNamespace() . '\\' . $targetName . 'Table',
                $source->getNamespace() . '\\' . $targetName,
                $targetName . 'Table',
                $targetName,
            ]);

        foreach ($candidates as $candidate) {
            $resolved = $architecture->getClass($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        foreach ($architecture->getClasses() as $class) {
            if ($class->getName() === $targetName || $class->getName() === $targetName . 'Table') {
                return $class;
            }
        }

        return null;
    }
}
