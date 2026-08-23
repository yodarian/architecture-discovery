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

            $lines[] = '## ' . $cluster['id'];
            $lines[] = '';
            $lines[] = '- Classes: ' . (int) ($metrics['classCount'] ?? count($classes));
            $lines[] = '- Internal dependencies: ' . (int) ($metrics['internalEdges'] ?? 0);
            $lines[] = '- Incoming dependencies (from other clusters): ' . (int) ($metrics['incomingEdges'] ?? 0);
            $lines[] = '- Outgoing dependencies (to other clusters): ' . (int) ($metrics['outgoingEdges'] ?? 0);
            $lines[] = '- Framework-tagged relations: ' . $this->countFrameworkTaggedRelations($architecture, $classes);
            $lines[] = '';
        }

        return implode("\n", $lines);
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
