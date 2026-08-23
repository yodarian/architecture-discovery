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
        return $this->renderDotFromView($this->viewBuilder->build($architecture));
    }

    public function renderSvg(Architecture $architecture): string
    {
        $view = $this->viewBuilder->build($architecture);
        $dot = $this->renderDotFromView($view);
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

    private function renderDotFromView(ArchitectureView $view): string
    {
        $lines = ['digraph architecture {', '  rankdir=LR;'];
        foreach ($view->getClasses() as $class) {
            $lines[] = '  ' . $this->quote($class['fqn']) . ' [label=' . $this->quote($class['name']) . '];';
        }
        foreach ($view->getDependencies() as $dependency) {
            $lines[] = '  ' . $this->quote($dependency['from'])
                . ' -> ' . $this->quote($dependency['to'])
                . ' [label=' . $this->quote($dependency['type'])
                . ', weight=' . $dependency['weight'] . '];';
        }
        $lines[] = '}';
        return implode("\n", $lines) . "\n";
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    private function fallbackSvg(ArchitectureView $view): string
    {
        $classes = $view->getClasses();
        $height = max(80, count($classes) * 28 + 30);
        $labels = [];
        foreach ($classes as $index => $class) {
            $labels[] = '<text x="12" y="' . (28 + $index * 28) . '">' . htmlspecialchars($class['fqn'], ENT_XML1) . '</text>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="' . $height . '" role="img" aria-label="Architecture graph">'
            . '<style>text { font: 14px sans-serif; }</style>' . implode('', $labels) . '</svg>';
    }
}
