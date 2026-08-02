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

    private const MACROS = __DIR__ . '/../../macros';

    public function __construct(Configuration $configuration)
    {
        $this->macroLoader = new MacroLoader();
        $this->transformer = new Transformer($configuration, $this->macroLoader);

        // Precedence, highest first, because the first matching rule wins:
        // explicitly supplied macros (--macro-file/--macro-dir), then those
        // discovered from installed packages, then the bundled ones. A library
        // shipping a rule for its own type therefore wins where the surface
        // syntax collides with a bundled rule (e.g. effect's IO vs Cats\IO),
        // and an explicit CLI macro overrides everything.
        $this->loadConfigurationMacros($configuration);
        $this->loadDiscoveredMacros();
        $this->macroLoader->loadFromDirectory(self::MACROS);
    }

    private function loadDiscoveredMacros(): void
    {
        foreach (MacroDiscovery::fromInstalledPackages()->discover() as $directory) {
            $this->macroLoader->loadFromDirectory($directory);
        }
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
            $transformed = $content === ''
                ? ''
                : $this->transformer->transform($content, $file);

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
