<?php
namespace ArchitectureDiscovery\Reporting;

use ArchitectureDiscovery\Domain\Model\Architecture;

/**
 * Generates a static HTML report from the architecture model and derived results.
 */
final class HtmlReportGenerator
{
    public function render(Architecture $architecture): string
    {
        $data = $architecture->toArray();
        $project = $data['project'];
        $metrics = $data['metrics'];
        $clusters = $data['clusters'];
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Architecture report</title>'
            . '<style>body{font:16px sans-serif;max-width:960px;margin:2rem auto;padding:0 1rem;color:#20252b}table{border-collapse:collapse;width:100%}th,td{border:1px solid #ccd2d8;padding:.5rem;text-align:left}h1{margin-bottom:.25rem}.metric{display:inline-block;margin:0 1rem 1rem 0}</style></head><body>'
            . '<h1>Architecture Overview</h1><p>' . $escape((string) $project['name']) . '</p>'
            . '<div class="metric"><strong>Classes</strong> ' . (int) ($metrics['classCount'] ?? 0) . '</div>'
            . '<div class="metric"><strong>Dependencies</strong> ' . (int) ($metrics['dependencyCount'] ?? 0) . '</div>'
            . '<div class="metric"><strong>CakePHP dependencies</strong> ' . (int) ($metrics['cakePhpDependencyCount'] ?? 0) . '</div>'
            . '<h2>Clusters</h2><table><thead><tr><th>Cluster</th><th>Classes</th><th>Cohesion</th><th>Coupling</th></tr></thead><tbody>';
        foreach ($clusters as $cluster) {
            $classList = implode(', ', array_map($escape, $cluster['classes']));
            $html .= '<tr><td>' . $escape((string) $cluster['id']) . '</td><td>' . $classList . ' (' . (int) $cluster['metrics']['classCount'] . ' classes)'
                . '</td><td>' . number_format((float) $cluster['metrics']['cohesion'], 2)
                . '</td><td>' . number_format((float) $cluster['metrics']['coupling'], 2) . '</td></tr>';
        }
        return $html . '</tbody></table></body></html>\n';
    }
}
