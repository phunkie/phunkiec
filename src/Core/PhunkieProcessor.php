<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

use Syn\Core\Configuration;
use Syn\Macro\MacroLoader;
use Syn\Transformer\Transformer;
use Symfony\Component\Finder\Finder;

class PhunkieProcessor
{
    private Transformer $transformer;
    private MacroLoader $macroLoader;

    public function __construct(Configuration $configuration)
    {
        $this->macroLoader = new MacroLoader();
        $this->transformer = new Transformer($configuration, $this->macroLoader);

        $this->loadConfigurationMacros($configuration);
    }

    public function process(string $input, string $output): array
    {
        if (is_file($input)) {
            return [$this->processFile($input, $output)];
        }

        return $this->processDirectory($input, $output);
    }

    public function getMacroLoader(): MacroLoader
    {
        return $this->macroLoader;
    }

    private function processFile(string $file, string $outputPath): array
    {
        $content = file_get_contents($file);
        $lines = substr_count($content, "\n") + 1;

        try {
            $content = $this->expandForComprehensions($content);
            $transformed = $this->transformer->transform($content, $file);

            $outputDir = dirname($outputPath);
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            file_put_contents($outputPath, $transformed);

            return [
                'file' => $file,
                'status' => 'ok',
                'lines' => $lines,
                'output' => $outputPath,
            ];
        } catch (\Throwable $e) {
            return [
                'file' => $file,
                'status' => 'error',
                'lines' => $lines,
                'output' => $outputPath,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function processDirectory(string $inputDir, string $outputDir): array
    {
        $finder = new Finder();
        $finder->files()->name('*.phunkie')->in($inputDir);

        $results = [];
        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $outputPath = $outputDir . '/' . preg_replace('/\.phunkie$/', '.php', $relativePath);

            $results[] = $this->processFile($file->getRealPath(), $outputPath);
        }

        return $results;
    }

    private function expandForComprehensions(string $code): string
    {
        return preg_replace_callback(
            '/for\s*\{\s*(\$\w+)\s*<-\s*(.+?)\s+(\$\w+)\s*<-\s*(.+?)\s*\}\s*yield\s+(.+?)(?=\s*;)/s',
            function (array $m): string {
                $binding = $m[1];
                $monad = trim($m[2]);
                $restBinding = $m[3];
                $restMonad = trim($m[4]);
                $yieldExpr = trim($m[5]);

                return $monad . '->flatMap(function(' . $binding . ') {' . "\n"
                    . '        return ' . $restMonad . '->map(function(' . $restBinding . ') use (' . $binding . ') {' . "\n"
                    . '            return ' . $yieldExpr . ';' . "\n"
                    . '        });' . "\n"
                    . '    })';
            },
            $code
        );
    }

    private function loadConfigurationMacros(Configuration $configuration): void
    {
        foreach ($configuration->getMacroFiles() as $file) {
            $this->macroLoader->loadFromFile($file);
        }

        foreach ($configuration->getMacroDirectories() as $directory) {
            $this->macroLoader->loadFromDirectory($directory);
        }
    }
}
