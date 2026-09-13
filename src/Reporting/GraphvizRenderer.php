<?php
namespace ArchitectureDiscovery\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Reporting\View\ArchitectureView;
use ArchitectureDiscovery\Reporting\View\ArchitectureViewBuilder;

/**
 * Renders the canonical architecture graph as Graphviz DOT or SVG.
 */
final class GraphvizRenderer
{
    public function __construct(private ArchitectureViewBuilder $viewBuilder = new ArchitectureViewBuilder())
    {
    }

    public function renderDot(Architecture $architecture): string
    {
        return $this->renderDotFromView($this->viewBuilder->build($architecture), $this->driftingClasses($architecture));
    }

    public function renderSvg(Architecture $architecture): string
    {
        $view = $this->viewBuilder->build($architecture);
        $dot = $this->renderDotFromView($view, $this->driftingClasses($architecture));
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

        return $this->fallbackSvg($view);
    }

    /** @param array<string, true> $driftingClasses */
    private function renderDotFromView(ArchitectureView $view, array $driftingClasses = []): string
    {
        $lines = ['digraph architecture {', '  rankdir=LR;'];
        foreach ($view->getClasses() as $class) {
            $style = isset($driftingClasses[$class['fqn']]) ? ', style="dotted"' : '';
            $lines[] = '  ' . $this->quote($class['fqn']) . ' [label=' . $this->quote($class['name']) . $style . '];';
        }
        foreach ($view->getDependencies() as $dependency) {
            $lines[] = '  ' . $this->quote($dependency['from'])
                . ' -> ' . $this->quote($dependency['to'])
                . ' [label=' . $this->quote($dependency['type'])
                . ', color=' . $this->quote($this->edgeColor($dependency, $view))
                . ', style=' . $this->quote($this->edgeStyle($dependency))
                . ', weight=' . $dependency['weight'] . '];';
        }
        $lines = array_merge($lines, $this->legendLines());
        $lines[] = '}';
        return implode("\n", $lines) . "\n";
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    public function renderFallbackSvg(Architecture $architecture): string
    {
        return $this->fallbackSvg($this->viewBuilder->build($architecture));
    }

    private function fallbackSvg(ArchitectureView $view): string
    {
        $classes = $view->getClasses();
        $legend = [
            'Interface target: green',
            'Concrete target: red',
            'Abstract target: amber',
            'Framework/external: gray',
            'Relationship type: edge label',
            'Namespace drift: dotted',
        ];
        $height = max(80, (count($classes) + count($legend) + 1) * 28 + 30);
        $labels = [];
        foreach ($classes as $index => $class) {
            $labels[] = '<text x="12" y="' . (28 + $index * 28) . '">' . htmlspecialchars($class['fqn'], ENT_XML1) . '</text>';
        }
        $offset = count($classes) * 28 + 28;
        foreach ($legend as $index => $label) {
            $labels[] = '<text x="12" y="' . ($offset + $index * 28) . '">' . htmlspecialchars($label, ENT_XML1) . '</text>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="' . $height . '" role="img" aria-label="Architecture graph">'
            . '<style>text { font: 14px sans-serif; }</style>' . implode('', $labels) . '</svg>';
    }

    /** @return string[] */
    private function legendLines(): array
    {
        return [
            '  subgraph "cluster_legend" {',
            '    label="Legend";',
            '    legend_interface [label="Interface target: green"];',
            '    legend_concrete [label="Concrete target: red"];',
            '    legend_abstract [label="Abstract target: amber"];',
            '    legend_framework [label="Framework/external: gray"];',
            '    legend_relationship [label="Relationship type: edge label"];',
            '    legend_confidence [label="Candidate confidence: border and text"];',
            '    legend_shared [label="Shared candidate: explicit text"];',
            '    legend_drift [label="Namespace drift: dotted"];',
            '    legend_unassigned [label="Unassigned: dashed"];',
            '  }',
        ];
    }

    /** @param array<string, mixed> $dependency */
    private function edgeColor(array $dependency, ArchitectureView $view): string
    {
        if (isset($dependency['metadata']['framework'])) {
            return '#718096';
        }
        $target = $dependency['to'];
        foreach ($view->getClasses() as $class) {
            if ($class['fqn'] !== $target) {
                continue;
            }
            if ($class['type'] === 'interface') {
                return '#38a169';
            }
            if ($class['abstract']) {
                return '#b7791f';
            }
            return '#c53030';
        }
        return '#718096';
    }

    /** @param array<string, mixed> $dependency */
    private function edgeStyle(array $dependency): string
    {
        return isset($dependency['metadata']['framework']) ? 'dotted' : 'solid';
    }

    /** @return array<string, true> */
    private function driftingClasses(Architecture $architecture): array
    {
        $drifting = [];
        foreach ($architecture->getModuleCandidates() as $candidate) {
            foreach ($candidate['namespace']['driftingMembers'] ?? [] as $member) {
                $drifting[$member['class']] = true;
            }
        }
        return $drifting;
    }
}
