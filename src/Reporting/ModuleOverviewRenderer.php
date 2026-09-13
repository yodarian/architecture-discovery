<?php
namespace ArchitectureDiscovery\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;

/**
 * Renders module candidates as a compact graph separate from the raw class graph.
 */
final class ModuleOverviewRenderer
{
    public function renderDot(Architecture $architecture): string
    {
        $candidates = $architecture->getModuleCandidates();
        usort($candidates, static fn(array $left, array $right): int => strcmp($left['id'], $right['id']));
        $sharedCandidates = $this->sharedCandidateIds($candidates);

        $classOwners = $this->classOwners($architecture, $candidates);
        $adjacentUnassigned = $this->adjacentUnassigned($architecture, $classOwners);
        $lines = [
            'digraph modules {',
            '  rankdir=LR;',
            '  compound=true;',
            '  graph [label="Module Overview", labelloc=t, fontsize=20];',
        ];

        foreach ($candidates as $candidate) {
            $confidence = (float) ($candidate['confidence'] ?? 0.0);
            $color = $confidence >= 0.75 ? '#2f855a' : '#b7791f';
            $lines[] = '  subgraph ' . $this->quote('cluster_' . $candidate['id']) . ' {';
            $sharedLabel = isset($sharedCandidates[$candidate['id']]) ? ', shared' : '';
            $lines[] = '    label=' . $this->quote($candidate['name'] . ' (confidence ' . number_format($confidence, 2) . $sharedLabel . ')') . ';';
            $lines[] = '    color=' . $this->quote($color) . ';';
            $lines[] = '    style=rounded;';

            foreach ($candidate['coreMembers'] as $className) {
                $class = $architecture->getClass($className);
                if ($class === null) {
                    continue;
                }
                $lines[] = '    ' . $this->quote($className)
                    . ' [label=' . $this->quote($class->getName()) . '];';
            }
            foreach ($candidate['relatedMembers'] as $className) {
                if (isset($classOwners[$className])) {
                    continue;
                }
                $class = $architecture->getClass($className);
                if ($class === null) {
                    continue;
                }
                $nodeId = $candidate['id'] . '_related_' . md5($className);
                $lines[] = '    ' . $this->quote($nodeId)
                    . ' [label=' . $this->quote($class->getName() . ' (related)')
                    . ', style=dotted, color="#718096"];';
            }
            foreach ($adjacentUnassigned[$candidate['id']] ?? [] as $className) {
                $class = $architecture->getClass($className);
                if ($class === null) {
                    continue;
                }
                $nodeId = $candidate['id'] . '_unassigned_' . md5($className);
                $lines[] = '    ' . $this->quote($nodeId)
                    . ' [label=' . $this->quote($class->getName() . ' (unassigned)')
                    . ', style=dashed, color="#718096"];';
            }
            $lines[] = '  }';
        }

        foreach ($this->aggregateDependencies($architecture, $classOwners) as $edge) {
            $lines[] = '  ' . $this->quote($edge['from']) . ' -> ' . $this->quote($edge['to'])
                . ' [label=' . $this->quote('count=' . $edge['count'] . ', strength=' . $edge['strength']) . '];';
        }

        $lines[] = '  subgraph ' . $this->quote('cluster_legend') . ' {';
        $lines[] = '    label="Legend";';
        $lines[] = '    legend_confidence [label="Border: confidence"];';
        $lines[] = '    legend_unassigned [label="Dashed: candidate-adjacent unassigned", style=dashed];';
        $lines[] = '    legend_edge [label="Edge: aggregated dependency count and strength"];';
        $lines[] = '  }';
        $lines[] = '}';

        return implode("\n", $lines) . "\n";
    }

    public function renderSvg(Architecture $architecture): string
    {
        $dot = $this->renderDot($architecture);
        $process = proc_open(
            ['dot', '-Tsvg'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );
        if (is_resource($process)) {
            fwrite($pipes[0], $dot);
            fclose($pipes[0]);
            $svg = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            if ($exitCode === 0 && is_string($svg) && $svg !== '') {
                return $svg;
            }
        }

        return $this->fallbackSvg($architecture);
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array<string, string>
     */
    private function classOwners(Architecture $architecture, array $candidates): array
    {
        $owners = [];
        foreach ($architecture->getClassMemberships() as $className => $membership) {
            if (($membership['primaryCandidate'] ?? null) !== null) {
                $owners[$className] = $membership['primaryCandidate'];
            }
        }
        foreach ($candidates as $candidate) {
            foreach ($candidate['coreMembers'] as $className) {
                $owners[$className] ??= $candidate['id'];
            }
        }
        return $owners;
    }

    /**
     * @param array<string, string> $classOwners
     * @return array<string, string[]>
     */
    private function adjacentUnassigned(Architecture $architecture, array $classOwners): array
    {
        $adjacent = [];
        $unassigned = array_fill_keys($architecture->getUnassignedClasses(), true);
        foreach ($architecture->getDependencies() as $dependency) {
            $from = $dependency->getFrom()->getFullyQualifiedName();
            $to = $dependency->getTo()->getFullyQualifiedName();
            if (isset($classOwners[$from], $unassigned[$to])) {
                $adjacent[$classOwners[$from]][$to] = true;
            }
            if (isset($classOwners[$to], $unassigned[$from])) {
                $adjacent[$classOwners[$to]][$from] = true;
            }
        }
        foreach ($adjacent as &$classes) {
            $classes = array_keys($classes);
            sort($classes);
        }
        unset($classes);
        return $adjacent;
    }

    /**
     * @param array<string, string> $classOwners
     * @return array<int, array{from: string, to: string, count: int, strength: int}>
     */
    private function aggregateDependencies(Architecture $architecture, array $classOwners): array
    {
        $aggregated = [];
        foreach ($architecture->getDependencies() as $dependency) {
            $fromClass = $dependency->getFrom()->getFullyQualifiedName();
            $toClass = $dependency->getTo()->getFullyQualifiedName();
            if (!isset($classOwners[$fromClass], $classOwners[$toClass]) || $classOwners[$fromClass] === $classOwners[$toClass]) {
                continue;
            }
            $key = $classOwners[$fromClass] . '>' . $classOwners[$toClass];
            $aggregated[$key]['from'] = $classOwners[$fromClass];
            $aggregated[$key]['to'] = $classOwners[$toClass];
            $aggregated[$key]['count'] = ($aggregated[$key]['count'] ?? 0) + 1;
            $aggregated[$key]['strength'] = ($aggregated[$key]['strength'] ?? 0) + $dependency->getWeight();
        }
        $edges = array_values($aggregated);
        usort($edges, static fn(array $left, array $right): int => strcmp(
            $left['from'] . '>' . $left['to'],
            $right['from'] . '>' . $right['to']
        ));
        return $edges;
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     * @return array<string, true>
     */
    private function sharedCandidateIds(array $candidates): array
    {
        $consumers = [];
        foreach ($candidates as $candidate) {
            foreach ($candidate['relatedMembers'] as $relatedMember) {
                foreach ($candidates as $sharedCandidate) {
                    if (in_array($relatedMember, $sharedCandidate['coreMembers'], true)) {
                        $consumers[$sharedCandidate['id']][$candidate['id']] = true;
                    }
                }
            }
        }
        $shared = [];
        foreach ($consumers as $candidateId => $candidateConsumers) {
            if (count($candidateConsumers) >= 2) {
                $shared[$candidateId] = true;
            }
        }
        return $shared;
    }

    private function fallbackSvg(Architecture $architecture): string
    {
        $lines = ['Module Overview', 'Legend'];
        foreach ($architecture->getModuleCandidates() as $candidate) {
            $lines[] = $candidate['name'] . ' (confidence ' . number_format((float) $candidate['confidence'], 2) . ')';
        }
        $height = max(80, count($lines) * 28 + 20);
        $labels = [];
        foreach ($lines as $index => $line) {
            $labels[] = '<text x="12" y="' . (24 + $index * 28) . '">' . htmlspecialchars($line, ENT_XML1) . '</text>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="' . $height . '" role="img" aria-label="Module overview">'
            . '<style>text { font: 14px sans-serif; }</style>' . implode('', $labels) . '</svg>';
    }
}