<?php
namespace ArchitectureDiscovery\Analysis;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\Dependency;

/**
 * Calculates lightweight comparison metrics from the canonical architecture graph.
 */
final class ArchitectureMetricsCalculator
{
    /**
     * @return array<string, mixed>
     */
    public function calculate(Architecture $architecture): array
    {
        $classMetrics = [];
        foreach ($architecture->getClasses() as $class) {
            $name = $class->getFullyQualifiedName();
            $classMetrics[$name] = [
                'incoming' => 0,
                'outgoing' => 0,
                'dependencyCount' => 0,
            ];
        }

        $cakePhpDependencyCount = 0;
        foreach ($architecture->getDependencies() as $dependency) {
            $from = $dependency->getFrom()->getFullyQualifiedName();
            $to = $dependency->getTo()->getFullyQualifiedName();
            if (isset($classMetrics[$from])) {
                $classMetrics[$from]['outgoing']++;
                $classMetrics[$from]['dependencyCount']++;
            }
            if (isset($classMetrics[$to])) {
                $classMetrics[$to]['incoming']++;
                $classMetrics[$to]['dependencyCount']++;
            }
            if (in_array($dependency->getType(), [Dependency::TYPE_ORM_RELATION, Dependency::TYPE_DYNAMIC_CALL], true)) {
                $cakePhpDependencyCount++;
            }
        }

        return [
            'classCount' => count($architecture->getClasses()),
            'dependencyCount' => count($architecture->getDependencies()),
            'cakePhpDependencyCount' => $cakePhpDependencyCount,
            'classes' => $classMetrics,
        ];
    }
}
