<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Console;

use Phunkie\Compiler\Core\PhunkieProcessor;
use Syn\Core\Configuration;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CompileCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('compile')
            ->setDescription('Compile .phunkie files into standard PHP')
            ->addArgument('input', InputArgument::REQUIRED, 'Input file or directory')
            ->addOption('out', 'o', InputOption::VALUE_REQUIRED, 'Output file or directory')
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

        $configuration = new Configuration();

        foreach ($input->getOption('macro-dir') as $dir) {
            $configuration->addMacroDirectory($dir);
        }

        foreach ($input->getOption('macro-file') as $file) {
            $configuration->addMacroFile($file);
        }

        $processor = new PhunkieProcessor($configuration);
        $results = $processor->process($inputPath, $outputPath);

        $errors = 0;
        foreach ($results as $result) {
            if ($result['status'] === 'error') {
                $errors++;
                $output->writeln("<error>ERROR {$result['file']}: {$result['error']}</error>");
            } elseif ($output->isVerbose()) {
                $output->writeln("<info>OK</info> {$result['file']} ({$result['lines']} lines) → {$result['output']}");
            }
        }

        $total = count($results);
        $ok = $total - $errors;

        if ($errors === 0) {
            $output->writeln("<info>Compiled {$ok} file(s) successfully.</info>");
        } else {
            $output->writeln("<comment>Compiled {$ok}/{$total} file(s). {$errors} error(s).</comment>");
        }

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
