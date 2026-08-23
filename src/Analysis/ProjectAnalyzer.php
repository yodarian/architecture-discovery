<?php
namespace ArchitectureDiscovery\Analysis;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ClassEntity;
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Infrastructure\Analyzer\DetectedRelationship;
use ArchitectureDiscovery\Infrastructure\Analyzer\Framework;
use ArchitectureDiscovery\Infrastructure\Analyzer\FrameworkDetectorRegistry;
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
        $detectors = (new FrameworkDetectorRegistry())->detectorsFor($projectPath);
        $frameworkRelationships = [];
        $classCount = 0;

        foreach ($files as $file) {
            try {
                $classes = $extractor->extractFromFile($file->getRealPath());
                foreach ($classes as $class) {
                    $architecture->addClass($class);
                    $classCount++;
                }
                foreach ($detectors as $detector) {
                    foreach ($detector->analyzeFile($file->getRealPath()) as $relationship) {
                        $frameworkRelationships[] = $relationship->withFramework($detector->framework());
                    }
                }
            } catch (\Exception $e) {
                $onProgress("Warning: Failed to parse {$file->getFilename()}: {$e->getMessage()}");
            }
        }

        $this->addStructuralDependencies($architecture);
        $this->addFrameworkDependencies($architecture, $frameworkRelationships);

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

            $this->addClassifiedTypeDependencies(
                $architecture,
                $class,
                $class->getPropertyTypeDependencies(),
                Dependency::TYPE_PROPERTY_TYPE,
                2
            );
            $this->addClassifiedTypeDependencies(
                $architecture,
                $class,
                $class->getParameterTypeDependencies(),
                Dependency::TYPE_PARAMETER_TYPE,
                1
            );
            $this->addClassifiedTypeDependencies(
                $architecture,
                $class,
                $class->getReturnTypeDependencies(),
                Dependency::TYPE_RETURN_TYPE,
                1
            );
            $this->addClassifiedTypeDependencies(
                $architecture,
                $class,
                $class->getStaticCallDependencies(),
                Dependency::TYPE_METHOD_CALL,
                1
            );

            $structuralDependencies = array_merge(
                $class->getInterfaces(),
                $class->getTraits(),
                $class->getExtends() !== null ? [$class->getExtends()] : [],
                $class->getPropertyTypeDependencies(),
                $class->getParameterTypeDependencies(),
                $class->getReturnTypeDependencies(),
                $class->getStaticCallDependencies()
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
     * @param string[] $typeNames
     */
    private function addClassifiedTypeDependencies(
        Architecture $architecture,
        ClassEntity $class,
        array $typeNames,
        string $type,
        int $weight
    ): void {
        foreach ($typeNames as $typeName) {
            $target = $architecture->getClass($typeName);
            if ($target !== null && $target !== $class) {
                $architecture->addDependency(new Dependency($class, $target, $type, $weight));
            }
        }
    }

    /**
     * Add framework relationships when their target resolves to a discovered class.
     * Unresolved dynamic calls are intentionally omitted from graph edges but are
     * still detected by the framework detectors for future reporting.
     *
     * @param DetectedRelationship[] $relationships
     */
    private function addFrameworkDependencies(Architecture $architecture, array $relationships): void
    {
        foreach ($relationships as $relationship) {
            if ($relationship->target === null) {
                continue;
            }

            $from = $architecture->getClass($relationship->from);
            $to = $this->resolveFrameworkTarget(
                $architecture,
                $relationship->from,
                $relationship->target,
                $relationship->framework
            );
            if ($from === null || $to === null || $from === $to) {
                continue;
            }

            $metadata = [
                ...$relationship->metadata,
                'framework' => $relationship->framework->value,
                'target' => $relationship->target,
            ];
            $architecture->addDependency(new Dependency(
                $from,
                $to,
                $relationship->type,
                $relationship->weight,
                $metadata
            ));
        }
    }

    private function resolveFrameworkTarget(
        Architecture $architecture,
        string $from,
        string $targetName,
        Framework $framework
    ): ?ClassEntity {
        $source = $architecture->getClass($from);
        if ($source === null) {
            return null;
        }

        $targetName = ltrim($targetName, '\\');
        $candidates = str_contains($targetName, '\\')
            ? [$targetName]
            : $this->buildTargetCandidates($source, $targetName, $framework);

        foreach ($candidates as $candidate) {
            $resolved = $architecture->getClass($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        foreach ($architecture->getClasses() as $class) {
            if ($class->getName() === $targetName) {
                return $class;
            }
            if ($framework === Framework::CakePhp && $class->getName() === $targetName . 'Table') {
                return $class;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function buildTargetCandidates(ClassEntity $source, string $targetName, Framework $framework): array
    {
        if ($framework === Framework::CakePhp) {
            return array_filter([
                $source->getNamespace() . '\\' . $targetName . 'Table',
                $source->getNamespace() . '\\' . $targetName,
                $targetName . 'Table',
                $targetName,
            ]);
        }

        return array_filter([
            $source->getNamespace() . '\\' . $targetName,
            $targetName,
        ]);
    }
}
