<?php
namespace ArchitectureDiscovery\Application\Command;

// Define the Symfony Console command only if the Console component is available.
if (class_exists('Symfony\\Component\\Console\\Command\\Command')) {
    final class BootstrapContextCommand extends \Symfony\Component\Console\Command\Command
    {
        protected static $defaultName = 'app:bootstrap-context';
        protected static $defaultDescription = 'Create a CONTEXT.md in a target project from the bundled template.';

        protected function configure(): void
        {
            $this->addArgument('target', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'Path to the target project directory');
        }

        protected function execute(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): int
        {
            $target = $input->getArgument('target');
            if (!is_dir($target)) {
                $output->writeln("<error>Target path does not exist or is not a directory: {$target}</error>");
                return 2;
            }

            $scriptDir = dirname(__DIR__, 3);
            $template = realpath($scriptDir . '/resources/templates/CONTEXT.md');
            if ($template === false || !is_file($template)) {
                $output->writeln('<error>Template not found at resources/templates/CONTEXT.md</error>');
                return 3;
            }

            $dest = rtrim($target, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'CONTEXT.md';
            if (file_exists($dest)) {
                $output->writeln("<comment>CONTEXT.md already exists at target (will not overwrite): {$dest}</comment>");
                return 1;
            }

            $content = file_get_contents($template);
            if ($content === false) {
                $output->writeln('<error>Failed to read template.</error>');
                return 3;
            }

            $projectName = basename(realpath($target));
            $projectPath = realpath($target);

            $framework = 'none';
            if (is_dir($target . '/src/Controller') || is_dir($target . '/src/Model')) {
                $framework = 'cakephp';
            } else {
                $composerJson = $target . '/composer.json';
                if (is_file($composerJson)) {
                    $cj = json_decode(file_get_contents($composerJson), true);
                    if (is_array($cj)) {
                        $s = json_encode($cj);
                        if (stripos($s, 'cakephp') !== false) {
                            $framework = 'cakephp';
                        }
                    }
                }
            }

            $replacements = [
                '<project-name>' => $projectName,
                '<project-path>' => $projectPath,
                '<cakephp|none>' => $framework,
                '<date>' => date('Y-m-d'),
            ];

            $out = str_replace(array_keys($replacements), array_values($replacements), $content);

            if (file_put_contents($dest, $out) === false) {
                $output->writeln("<error>Failed to write CONTEXT.md to {$dest}</error>");
                return 4;
            }

            $output->writeln("<info>Created CONTEXT.md at: {$dest}</info>");
            return 0;
        }
    }
}
