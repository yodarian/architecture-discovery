<?php
namespace ArchitectureDiscovery\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;

/**
 * Renders the canonical architecture graph as Graphviz DOT or SVG.
 */
final class GraphvizRenderer
{
    public function renderDot(Architecture $architecture): string
    {
        $lines = ['digraph architecture {', '  rankdir=LR;'];
        foreach ($architecture->getClasses() as $class) {
            $name = $class->getFullyQualifiedName();
            $lines[] = '  ' . $this->quote($name) . ' [label=' . $this->quote($class->getName()) . '];';
        }
        foreach ($architecture->getDependencies() as $dependency) {
            $lines[] = '  ' . $this->quote($dependency->getFrom()->getFullyQualifiedName())
                . ' -> ' . $this->quote($dependency->getTo()->getFullyQualifiedName())
                . ' [label=' . $this->quote($dependency->getType())
                . ', weight=' . $dependency->getWeight() . '];';
        }
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

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"") . '"';
    }

    private function fallbackSvg(Architecture $architecture): string
    {
        $height = max(80, count($architecture->getClasses()) * 28 + 30);
        $labels = [];
        foreach ($architecture->getClasses() as $index => $class) {
            $labels[] = '<text x="12" y="' . (28 + $index * 28) . '">' . htmlspecialchars($class->getFullyQualifiedName(), ENT_XML1) . '</text>';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="900" height="' . $height . '" role="img" aria-label="Architecture graph">'
            . '<style>text { font: 14px sans-serif; }</style>' . implode('', $labels) . '</svg>';
    }
}
