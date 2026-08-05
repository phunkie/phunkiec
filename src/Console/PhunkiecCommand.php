<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Console;

use Phunkie\Compiler\Core\OutputDirectory;
use Phunkie\Compiler\Core\PhunkieProcessor;
use Phunkie\Compiler\Watch\WatcherInterface;
use Syn\Core\Configuration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * There is only one thing phunkiec does, so there is no command to choose
 * between and none to name. It carries the name of the binary, which is what
 * Symfony prints as the program in usage and in error synopses.
 */
final class PhunkiecCommand extends Command
{
    public function __construct(
        private readonly WatcherInterface $watcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('phunkiec')
            ->setDescription('Compile .phunkie files into standard PHP')
            ->addArgument('input', InputArgument::REQUIRED, 'Input file or directory')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output file or directory')
            ->addOption('watch', 'w', InputOption::VALUE_NONE, 'Recompile a source as soon as it is saved')
            ->addOption('macro-dir', 'm', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Additional macro directories')
            ->addOption('macro-file', 'f', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'Additional macro files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $inputPath = $input->getArgument('input');
        $outputPath = $input->getOption('out');

        if ($outputPath === null) {
            $output->writeln('<error>The --out option is required.</error>');

            return Command::FAILURE;
        }

        if (!file_exists($inputPath)) {
            $output->writeln("<error>Input path not found: {$inputPath}</error>");

            return Command::FAILURE;
        }

        $processor = new PhunkieProcessor($this->configuration($input));
        $errors = $this->reportBatch($processor->process($inputPath, $outputPath), $output);

        if (!$input->getOption('watch')) {
            return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        if (!is_dir($inputPath)) {
            $output->writeln('<error>Watching needs a directory of sources, not a single file.</error>');

            return Command::FAILURE;
        }

        return $this->watch($processor, $inputPath, $outputPath, $output);
    }

    /**
     * A watch outlives its failures. Compiling a single source already reports
     * a broken one as a result rather than throwing, so a source saved
     * half-written stops nothing: the next save compiles it.
     */
    private function watch(PhunkieProcessor $processor, string $inputPath, string $outputPath, OutputInterface $output): int
    {
        $outputDirectory = new OutputDirectory($outputPath);

        $output->writeln(sprintf('<info>Watching</info> %s. Press Ctrl+C to stop.', $inputPath));

        $recompile = function (array $changed) use ($processor, $inputPath, $outputDirectory, $output): void {
            foreach ($changed as $relativePath) {
                $results = $processor->process(
                    $inputPath . '/' . $relativePath,
                    $outputDirectory->forSource($relativePath)
                );

                foreach ($results as $result) {
                    $this->reportResult($result, $output);
                }
            }
        };

        $this->watcher->watch($inputPath, $recompile);

        return Command::SUCCESS;
    }

    private function configuration(InputInterface $input): Configuration
    {
        $configuration = new Configuration();

        foreach ($input->getOption('macro-dir') as $dir) {
            $configuration->addMacroDirectory($dir);
        }

        foreach ($input->getOption('macro-file') as $file) {
            $configuration->addMacroFile($file);
        }

        return $configuration;
    }

    /**
     * @param list<array<string, mixed>> $results
     *
     * @return int how many failed
     */
    private function reportBatch(array $results, OutputInterface $output): int
    {
        $errors = 0;

        foreach ($results as $result) {
            if ($result['status'] === 'error') {
                $errors++;
                $this->reportResult($result, $output);

                continue;
            }

            if ($output->isVerbose()) {
                $this->reportResult($result, $output);
            }
        }

        $total = count($results);
        $ok = $total - $errors;

        if ($errors === 0) {
            $output->writeln("<info>Compiled {$ok} file(s) successfully.</info>");

            return 0;
        }

        $output->writeln("<comment>Compiled {$ok}/{$total} file(s). {$errors} error(s).</comment>");

        return $errors;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function reportResult(array $result, OutputInterface $output): void
    {
        if ($result['status'] === 'error') {
            $output->writeln("<error>ERROR {$result['file']}: {$result['error']}</error>");

            return;
        }

        $output->writeln("<info>OK</info> {$result['file']} ({$result['lines']} lines) → {$result['output']}");
    }
}
