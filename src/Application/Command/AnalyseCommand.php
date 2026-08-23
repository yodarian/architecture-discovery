<?php
namespace ArchitectureDiscovery\Application\Command;

use DateTime;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ArchitectureDiscovery\Domain\Model\Architecture;
use ArchitectureDiscovery\Domain\Model\ProjectMetadata;
use ArchitectureDiscovery\Analysis\ProjectAnalyzer;
use ArchitectureDiscovery\Analysis\ArchitectureMetricsCalculator;
use ArchitectureDiscovery\Clustering\ConnectedComponentsClusterer;
use ArchitectureDiscovery\Reporting\GraphvizRenderer;
use ArchitectureDiscovery\Reporting\HtmlReportGenerator;

/**
 * Analyse command scans a PHP project and generates the canonical architecture model.
 * Output is written to this tool's own out/<project-name> directory by default,
 * never into the analyzed project, unless --output overrides the location.
 */
final class AnalyseCommand extends Command
{
    protected static $defaultName = 'analyse';
    protected static $defaultDescription = 'Analyze a PHP project and generate the architecture model (architecture.json).';

    protected function configure(): void
    {
        $this->setHelp(
            'This command scans a PHP project directory, extracts class and dependency information, '
            . 'and produces a canonical architecture.json artifact containing the complete analysis model.'
        );

        $this->addArgument(
            'path',
            InputArgument::REQUIRED,
            'Path to the PHP project directory to analyze'
        );

        $this->addOption(
            'output',
            'o',
            InputOption::VALUE_REQUIRED,
            'Output directory for architecture.json (defaults to out/<project-name> inside this repo)',
            null
        );

        $this->addOption(
            'exclude',
            'e',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'Additional directories to exclude from scanning (comma-separated or multiple -e flags)'
        );

        $this->addOption(
            'format',
            'f',
            InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED,
            'Output formats (json, csv, html) - currently only json is supported'
        );

        $this->addOption(
            'model-version',
            null,
            InputOption::VALUE_REQUIRED,
            'Version string for the architecture model',
            '1.0.0'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projectPath = $input->getArgument('path');

        // Validate project path
        $realPath = realpath($projectPath);
        if (!$realPath || !is_dir($realPath)) {
            $output->writeln("<error>Project path does not exist or is not a directory: {$projectPath}</error>");
            return 1;
        }

        $projectPath = $realPath;
        $output->writeln("<info>Analyzing project: {$projectPath}</info>");

        // Determine output directory: never inside the analyzed project by default.
        $outputOption = $input->getOption('output');
        $outputDir = $outputOption ?? dirname(__DIR__, 3) . '/out/' . basename($projectPath);
        if (!is_dir($outputDir)) {
            if (!@mkdir($outputDir, 0755, true)) {
                $output->writeln("<error>Failed to create output directory: {$outputDir}</error>");
                return 1;
            }
        }

        $formats = $this->getFormats($input->getOption('format') ?? []);
        if ($formats === null) {
            $output->writeln('<error>Unsupported format. Supported formats: json, dot, svg, html.</error>');
            return 1;
        }

        // Get exclusion list
        $excludeDirs = $input->getOption('exclude') ?? [];
        if (!empty($excludeDirs)) {
            $excludeDirs = array_filter(array_map('trim', $excludeDirs));
        }

        // Create metadata
        $projectName = basename($projectPath);
        $projectVersion = $input->getOption('model-version');
        $generatedAt = new DateTime();

        // Try to read composer.json for additional metadata
        $composerData = $this->readComposerJson($projectPath);

        if (isset($composerData['name'])) {
            $projectName = $composerData['name'];
        }
        if (isset($composerData['version'])) {
            $projectVersion = $composerData['version'];
        }

        $metadata = new ProjectMetadata($projectName, $projectPath, $projectVersion, $generatedAt, $composerData);
        $architecture = new Architecture($metadata);

        // Scan and analyze
        try {
            $this->analyzeProject($architecture, $projectPath, $excludeDirs, $output);
        } catch (\Exception $e) {
            $output->writeln("<error>Analysis failed: {$e->getMessage()}</error>");
            if ($output->isVerbose()) {
                $output->writeln($e->getTraceAsString());
            }
            return 1;
        }

        // Output results
        $outputFile = $outputDir . DIRECTORY_SEPARATOR . 'architecture.json';
        $architecture->setMetrics((new ArchitectureMetricsCalculator())->calculate($architecture));
        $architecture->setClusters((new ConnectedComponentsClusterer())->cluster($architecture));
        $renderer = new GraphvizRenderer();
        $artifacts = [];
        if (in_array('dot', $formats, true)) {
            $artifacts['graph.dot'] = $renderer->renderDot($architecture);
        }
        if (in_array('svg', $formats, true)) {
            $artifacts['graph.svg'] = $renderer->renderSvg($architecture);
        }
        if (in_array('html', $formats, true)) {
            $artifacts['index.html'] = (new HtmlReportGenerator())->render($architecture);
        }
        foreach ($artifacts as $name => $content) {
            if (file_put_contents($outputDir . DIRECTORY_SEPARATOR . $name, $content) === false) {
                $output->writeln("<error>Failed to write {$name}</error>");
                return 1;
            }
        }

        $success = $this->writeArchitectureJson($architecture, $outputFile);
        if (!$success) {
            $output->writeln("<error>Failed to write architecture.json</error>");
            return 1;
        }

        $classCount = count($architecture->getClasses());
        $dependencyCount = count($architecture->getDependencies());
        $output->writeln("<info>Analysis complete!</info>");
        $output->writeln("  Classes/Interfaces/Traits: {$classCount}");
        $output->writeln("  Dependencies: {$dependencyCount}");
        $output->writeln("  Clusters: " . count($architecture->getClusters()));
        $output->writeln("  Output: {$outputFile}");

        return 0;
    }

    /**
     * @param array<int, string> $requestedFormats
     * @return string[]|null
     */
    private function getFormats(array $requestedFormats): ?array
    {
        if ($requestedFormats === []) {
            return ['json', 'dot', 'svg', 'html'];
        }

        $formats = [];
        foreach ($requestedFormats as $requestedFormat) {
            foreach (explode(',', $requestedFormat) as $format) {
                $format = strtolower(trim($format));
                if ($format === '' || !in_array($format, ['json', 'dot', 'svg', 'html'], true)) {
                    return null;
                }
                $formats[] = $format;
            }
        }

        return array_values(array_unique($formats));
    }

    /**
     * Analyze the project and populate the architecture model.
     */
    private function analyzeProject(
        Architecture $architecture,
        string $projectPath,
        array $excludeDirs,
        OutputInterface $output
    ): void {
        (new ProjectAnalyzer())->analyze(
            $architecture,
            $projectPath,
            $excludeDirs,
            static function (string $message) use ($output): void {
                if ($output->isVerbose()) {
                    $output->writeln("<comment>{$message}</comment>");
                }
            }
        );
    }

    /**
     * Write the architecture model to architecture.json.
     */
    private function writeArchitectureJson(Architecture $architecture, string $filePath): bool
    {
        $data = $architecture->toArray();
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        return file_put_contents($filePath, $json) !== false;
    }

    /**
     * Read and parse composer.json if it exists.
     *
     * @return array<string, mixed>
     */
    private function readComposerJson(string $projectPath): array
    {
        $composerFile = $projectPath . DIRECTORY_SEPARATOR . 'composer.json';

        if (!is_file($composerFile)) {
            return [];
        }

        $content = file_get_contents($composerFile);
        if ($content === false) {
            return [];
        }

        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }
}
