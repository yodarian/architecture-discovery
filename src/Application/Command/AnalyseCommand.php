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
use ArchitectureDiscovery\Domain\Model\Dependency;
use ArchitectureDiscovery\Infrastructure\Scanner\FileScanner;
use ArchitectureDiscovery\Infrastructure\Parser\PhpClassExtractor;
use ArchitectureDiscovery\Infrastructure\Analyzer\CakePhpAnalyzer;
use ArchitectureDiscovery\Analysis\ArchitectureMetricsCalculator;
use ArchitectureDiscovery\Clustering\ConnectedComponentsClusterer;
use ArchitectureDiscovery\Reporting\GraphvizRenderer;
use ArchitectureDiscovery\Reporting\HtmlReportGenerator;

/**
 * Analyse command scans a PHP project and generates the canonical architecture model.
 * The output is written to architecture.json in the project directory.
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
            'Output directory for architecture.json (defaults to project root)',
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

        // Determine output directory
        $outputDir = $input->getOption('output') ?? $projectPath;
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
        if ($output->isVerbose()) {
            $output->writeln("<comment>Scanning for PHP files...</comment>");
        }

        $scanner = new FileScanner($excludeDirs);
        $files = $scanner->scanDirectory($projectPath);

        if ($output->isVerbose()) {
            $output->writeln("  Found " . count($files) . " PHP files");
        }

        if ($output->isVerbose()) {
            $output->writeln("<comment>Extracting classes from files...</comment>");
        }

        $extractor = new PhpClassExtractor($projectPath);
        $cakePhpAnalyzer = new CakePhpAnalyzer();
        $cakeRelationships = [];
        $classCount = 0;

        foreach ($files as $file) {
            try {
                $classes = $extractor->extractFromFile($file->getRealPath());
                foreach ($classes as $class) {
                    $architecture->addClass($class);
                    $classCount++;
                }
                $cakeRelationships = array_merge(
                    $cakeRelationships,
                    $cakePhpAnalyzer->analyzeFile($file->getRealPath())
                );
            } catch (\Exception $e) {
                if ($output->isVerbose()) {
                    $output->writeln("<comment>  Warning: Failed to parse {$file->getFilename()}: {$e->getMessage()}</comment>");
                }
            }
        }

        $this->addStructuralDependencies($architecture);
        $this->addCakePhpDependencies($architecture, $cakeRelationships);

        if ($output->isVerbose()) {
            $output->writeln("  Extracted {$classCount} classes, interfaces, and traits");
        }
    }

    private function addStructuralDependencies(Architecture $architecture): void
    {
        foreach ($architecture->getClasses() as $class) {
            if ($class->getExtends() !== null) {
                $target = $architecture->getClass($class->getExtends());
                if ($target !== null) {
                    $architecture->addDependency(new Dependency($class, $target, Dependency::TYPE_EXTENDS, 3));
                }
            }

            foreach ($class->getInterfaces() as $interfaceName) {
                $target = $architecture->getClass($interfaceName);
                if ($target !== null) {
                    $architecture->addDependency(new Dependency($class, $target, Dependency::TYPE_IMPLEMENTS, 2));
                }
            }

            foreach ($class->getTraits() as $traitName) {
                $target = $architecture->getClass($traitName);
                if ($target !== null) {
                    $architecture->addDependency(new Dependency($class, $target, Dependency::TYPE_TRAIT_USE, 2));
                }
            }

            $structuralDependencies = array_merge(
                $class->getInterfaces(),
                $class->getTraits(),
                $class->getExtends() !== null ? [$class->getExtends()] : []
            );
            foreach ($class->getTypeDependencies() as $typeName) {
                if (in_array($typeName, $structuralDependencies, true)) {
                    continue;
                }

                $target = $architecture->getClass($typeName);
                if ($target !== null && $target !== $class) {
                    $architecture->addDependency(new Dependency($class, $target, Dependency::TYPE_USES, 1));
                }
            }
        }
    }

    /**
     * Add CakePHP relationships when their target resolves to a discovered class.
     * Unresolved dynamic calls are intentionally omitted from graph edges but are
     * still detected by CakePhpAnalyzer for future reporting.
     *
     * @param array<int, array{from: string, target: string|null, type: string, weight: int, metadata: array<string, mixed>}> $relationships
     */
    private function addCakePhpDependencies(Architecture $architecture, array $relationships): void
    {
        foreach ($relationships as $relationship) {
            if ($relationship['target'] === null) {
                continue;
            }

            $from = $architecture->getClass($relationship['from']);
            $to = $this->resolveCakePhpTarget($architecture, $relationship['from'], $relationship['target']);
            if ($from === null || $to === null || $from === $to) {
                continue;
            }

            $metadata = $relationship['metadata'];
            $metadata['target'] = $relationship['target'];
            $architecture->addDependency(new Dependency(
                $from,
                $to,
                $relationship['type'],
                $relationship['weight'],
                $metadata
            ));
        }
    }

    private function resolveCakePhpTarget(Architecture $architecture, string $from, string $targetName): ?\ArchitectureDiscovery\Domain\Model\ClassEntity
    {
        $source = $architecture->getClass($from);
        if ($source === null) {
            return null;
        }

        $targetName = ltrim($targetName, '\\');
        $candidates = str_contains($targetName, '\\')
            ? [$targetName]
            : array_filter([
                $source->getNamespace() . '\\' . $targetName . 'Table',
                $source->getNamespace() . '\\' . $targetName,
                $targetName . 'Table',
                $targetName,
            ]);

        foreach ($candidates as $candidate) {
            $resolved = $architecture->getClass($candidate);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        foreach ($architecture->getClasses() as $class) {
            if ($class->getName() === $targetName || $class->getName() === $targetName . 'Table') {
                return $class;
            }
        }

        return null;
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
