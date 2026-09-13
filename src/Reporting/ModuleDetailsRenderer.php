<?php
namespace ArchitectureDiscovery\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;

/**
 * Builds a candidate-focused review representation and filtered class graph.
 */
final class ModuleDetailsRenderer
{
    /**
     * @return array<string, mixed>
     */
    public function build(Architecture $architecture, string $candidateId): array
    {
        $candidate = $this->findCandidate($architecture, $candidateId);
        if ($candidate === null) {
            return [
                'candidate' => null,
                'core' => [],
                'related' => [],
                'shared' => [],
                'unassigned' => [],
                'dependencies' => [],
            ];
        }

        $candidateIdsByClass = $this->candidateIdsByClass($architecture);
        $core = $this->describeClasses($architecture, $candidate['coreMembers'], $candidate);
        $relatedNames = $candidate['relatedMembers'];
        $related = $this->describeClasses($architecture, $relatedNames, $candidate);
        $sharedNames = array_values(array_filter(
            $relatedNames,
            static fn(string $className): bool => count($candidateIdsByClass[$className] ?? []) > 1
        ));
        $shared = $this->describeClasses($architecture, $sharedNames, $candidate);
        $unassignedNames = $this->adjacentUnassigned($architecture, $candidate['coreMembers']);

        return [
            'candidate' => $candidate,
            'core' => $core,
            'related' => $related,
            'shared' => $shared,
            'unassigned' => $this->describeClasses($architecture, $unassignedNames, $candidate),
            'dependencies' => $this->describeDependencies($architecture, array_unique(array_merge(
                $candidate['coreMembers'],
                $relatedNames,
                $unassignedNames
            ))),
        ];
    }

    /** @return string */
    public function renderDot(Architecture $architecture, string $candidateId): string
    {
        $details = $this->build($architecture, $candidateId);
        $lines = ['digraph module_detail {', '  rankdir=LR;'];
        foreach (['core', 'related', 'shared', 'unassigned'] as $section) {
            $lines[] = '  subgraph ' . $this->quote('cluster_' . $section) . ' {';
            $lines[] = '    label=' . $this->quote(ucfirst($section)) . ';';
            foreach ($details[$section] as $member) {
                $style = $section === 'unassigned' ? ', style="dashed"' : ($member['namespaceAlignment']['drift'] ? ', style="dotted"' : '');
                $lines[] = '    ' . $this->quote($member['class']) . ' [label=' . $this->quote($member['name'] . ' (' . $member['role'] . ')') . $style . '];';
            }
            $lines[] = '  }';
        }
        foreach ($details['dependencies'] as $dependency) {
            $lines[] = '  ' . $this->quote($dependency['from']) . ' -> ' . $this->quote($dependency['to'])
                . ' [label=' . $this->quote($dependency['type']) . ', weight=' . $dependency['weight'] . '];';
        }
        $lines[] = '}';
        return implode("\n", $lines) . "\n";
    }

    /** @return array<string, mixed>|null */
    private function findCandidate(Architecture $architecture, string $candidateId): ?array
    {
        foreach ($architecture->getModuleCandidates() as $candidate) {
            if ($candidate['id'] === $candidateId || $candidate['name'] === $candidateId) {
                return $candidate;
            }
        }
        return null;
    }

    /** @return array<string, string[]> */
    private function candidateIdsByClass(Architecture $architecture): array
    {
        $result = [];
        foreach ($architecture->getModuleCandidates() as $candidate) {
            foreach (array_merge($candidate['coreMembers'], $candidate['relatedMembers']) as $className) {
                $result[$className][] = $candidate['id'];
            }
        }
        return $result;
    }

    /** @param string[] $classNames @return array<int, array<string, mixed>> */
    private function describeClasses(Architecture $architecture, array $classNames, array $candidate): array
    {
        $descriptions = [];
        foreach ($classNames as $className) {
            $class = $architecture->getClass($className);
            if ($class === null) {
                continue;
            }
            $membership = $architecture->getClassMemberships()[$className] ?? [];
            $drift = null;
            foreach ($candidate['namespace']['driftingMembers'] ?? [] as $member) {
                if (($member['class'] ?? null) === $className) {
                    $drift = $member;
                    break;
                }
            }
            $descriptions[] = [
                'class' => $className,
                'name' => $class->getName(),
                'namespace' => $class->getNamespace(),
                'file' => $class->getFile(),
                'role' => $membership['role'] ?? 'unknown',
                'confidence' => $candidate['confidence'],
                'evidence' => $candidate['evidence'],
                'namespaceAlignment' => [
                    'drift' => $drift !== null,
                    'suggestedNamespace' => $drift['suggestedNamespace'] ?? null,
                    'evidence' => $drift['evidence'] ?? null,
                ],
            ];
        }
        usort($descriptions, static fn(array $left, array $right): int => strcmp($left['class'], $right['class']));
        return $descriptions;
    }

    /** @param string[] $scope @return array<int, array<string, mixed>> */
    private function describeDependencies(Architecture $architecture, array $scope): array
    {
        $members = array_fill_keys($scope, true);
        $dependencies = [];
        foreach ($architecture->getDependencies() as $dependency) {
            $from = $dependency->getFrom()->getFullyQualifiedName();
            $to = $dependency->getTo()->getFullyQualifiedName();
            if (!isset($members[$from]) && !isset($members[$to])) {
                continue;
            }
            $dependencies[] = $dependency->toArray();
        }
        return $dependencies;
    }

    /** @param string[] $core @return string[] */
    private function adjacentUnassigned(Architecture $architecture, array $core): array
    {
        $coreSet = array_fill_keys($core, true);
        $unassigned = array_fill_keys($architecture->getUnassignedClasses(), true);
        $result = [];
        foreach ($architecture->getDependencies() as $dependency) {
            $from = $dependency->getFrom()->getFullyQualifiedName();
            $to = $dependency->getTo()->getFullyQualifiedName();
            if (isset($coreSet[$from], $unassigned[$to])) {
                $result[$to] = true;
            }
            if (isset($coreSet[$to], $unassigned[$from])) {
                $result[$from] = true;
            }
        }
        $result = array_keys($result);
        sort($result);
        return $result;
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }
}