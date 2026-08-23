<?php
namespace ArchitectureDiscovery\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;

/**
 * Renders a compact Markdown summary of cluster boundaries and coupling at
 * module granularity, so an agent can orient in an unfamiliar codebase
 * without reading the full architecture.json or the source itself.
 */
final class ArchitectureMapRenderer
{
    public function render(Architecture $architecture): string
    {
        $lines = [
            '# Architecture Map',
            '',
            'Project: ' . $architecture->getMetadata()->getName(),
            'Clusters: ' . count($architecture->getClusters()),
            '',
        ];

        foreach ($architecture->getClusters() as $cluster) {
            $metrics = $cluster['metrics'] ?? [];
            $classes = $cluster['classes'] ?? [];
            $classCount = (int) ($metrics['classCount'] ?? count($classes));
            $internalEdges = (int) ($metrics['internalEdges'] ?? 0);
            $isolated = $classCount === 1 && $internalEdges === 0;

            $lines[] = '## ' . $cluster['id'] . ' — ' . $this->deriveLabel($architecture, $classes);
            $lines[] = '';
            $lines[] = '- Classes: ' . $classCount;
            $lines[] = '- Internal dependencies: ' . $internalEdges;
            $lines[] = '- Isolated: ' . ($isolated ? 'yes' : 'no');
            $lines[] = '- Framework-tagged relations: ' . $this->countFrameworkTaggedRelations($architecture, $classes);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * Derives a label from the longest common namespace prefix shared by a cluster's
     * member classes, so a cluster is identifiable without listing its classes.
     *
     * @param string[] $classes
     */
    private function deriveLabel(Architecture $architecture, array $classes): string
    {
        $namespaces = [];
        foreach ($classes as $fqn) {
            $class = $architecture->getClass($fqn);
            if ($class !== null) {
                $namespaces[] = $class->getNamespace();
            }
        }
        $namespaces = array_values(array_unique($namespaces));

        if ($namespaces === []) {
            return 'unknown';
        }
        if (count($namespaces) === 1) {
            return $namespaces[0];
        }

        $segmentLists = array_map(static fn(string $namespace) => explode('\\', $namespace), $namespaces);
        $common = $segmentLists[0];
        foreach (array_slice($segmentLists, 1) as $segments) {
            $length = 0;
            while ($length < count($common) && $length < count($segments) && $common[$length] === $segments[$length]) {
                $length++;
            }
            $common = array_slice($common, 0, $length);
            if ($common === []) {
                break;
            }
        }

        return $common === [] ? 'mixed namespaces' : implode('\\', $common);
    }

    /**
     * @param string[] $classes
     */
    private function countFrameworkTaggedRelations(Architecture $architecture, array $classes): int
    {
        $members = array_fill_keys($classes, true);
        $count = 0;
        foreach ($architecture->getDependencies() as $dependency) {
            $fromMember = isset($members[$dependency->getFrom()->getFullyQualifiedName()]);
            $toMember = isset($members[$dependency->getTo()->getFullyQualifiedName()]);
            if (($fromMember || $toMember) && isset($dependency->getMetadata()['framework'])) {
                $count++;
            }
        }
        return $count;
    }
}
