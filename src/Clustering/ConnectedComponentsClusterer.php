<?php
namespace ArchitectureDiscovery\Clustering;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\Dependency;

/**
 * Suggests module candidates using connected components of the dependency graph.
 */
final class ConnectedComponentsClusterer
{
    /**
     * @return array<int, array{id: string, classes: string[], metrics: array<string, float|int>}>
     */
    public function cluster(Architecture $architecture): array
    {
        $classNames = array_map(
            fn($class) => $class->getFullyQualifiedName(),
            $architecture->getClasses()
        );
        $adjacency = array_fill_keys($classNames, []);
        foreach ($architecture->getDependencies() as $dependency) {
            $from = $dependency->getFrom()->getFullyQualifiedName();
            $to = $dependency->getTo()->getFullyQualifiedName();
            $adjacency[$from][] = $to;
            $adjacency[$to][] = $from;
        }

        $components = [];
        $visited = [];
        foreach ($classNames as $className) {
            if (isset($visited[$className])) {
                continue;
            }
            $component = [];
            $queue = [$className];
            $visited[$className] = true;
            while ($queue !== []) {
                $current = array_shift($queue);
                $component[] = $current;
                foreach ($adjacency[$current] as $neighbor) {
                    if (!isset($visited[$neighbor])) {
                        $visited[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }
            sort($component);
            $components[] = $component;
        }

        usort($components, fn(array $left, array $right) => strcmp($left[0], $right[0]));
        $clusters = [];
        foreach ($components as $index => $component) {
            $clusters[] = [
                'id' => 'cluster-' . ($index + 1),
                'classes' => $component,
                'metrics' => $this->calculateMetrics($component, $architecture),
            ];
        }
        return $clusters;
    }

    /**
     * @param string[] $classes
     * @return array<string, float|int>
     */
    private function calculateMetrics(array $classes, Architecture $architecture): array
    {
        $members = array_fill_keys($classes, true);
        $internal = 0;
        $incoming = 0;
        $outgoing = 0;
        foreach ($architecture->getDependencies() as $dependency) {
            $fromInternal = isset($members[$dependency->getFrom()->getFullyQualifiedName()]);
            $toInternal = isset($members[$dependency->getTo()->getFullyQualifiedName()]);
            if ($fromInternal && $toInternal) {
                $internal++;
            } elseif ($toInternal) {
                $incoming++;
            } elseif ($fromInternal) {
                $outgoing++;
            }
        }
        $total = $internal + $incoming + $outgoing;
        return [
            'classCount' => count($classes),
            'internalEdges' => $internal,
            'incomingEdges' => $incoming,
            'outgoingEdges' => $outgoing,
            'cohesion' => $total > 0 ? $internal / $total : 1.0,
            'coupling' => $total > 0 ? ($incoming + $outgoing) / $total : 0.0,
        ];
    }
}
