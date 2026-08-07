<?php

declare(strict_types=1);

namespace Phunkie\Compiler\Core;

use Phunkie\Compiler\Generics\Erasure;
use Phunkie\Compiler\Generics\GuardInjector;
use RuntimeException;
use Syn\Core\Configuration;
use Syn\Macro\MacroLoader;
use Syn\Transformer\Transformer;

class PhunkieProcessor
{
    private Transformer $transformer;
    private MacroLoader $macroLoader;
    private Erasure $erasure;
    private GuardInjector $guards;
    private SyntaxCheck $syntax;
    private Grammar $grammar;
    private OpeningTag $openingTag;

    private const MACROS = __DIR__ . '/../../macros';

    public function __construct(Configuration $configuration)
    {
        $this->macroLoader = new MacroLoader();
        $this->transformer = new Transformer($this->macroLoader);
        $this->erasure = new Erasure();
        $this->guards = new GuardInjector();
        $this->syntax = new SyntaxCheck();
        $this->grammar = new Grammar();
        $this->openingTag = new OpeningTag();

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
        $written = (string) file_get_contents($file);
        $content = $this->openingTag->ensure($written);
        $lines = substr_count($content, "\n") + 1;

        try {
            // Before anything is erased or transformed, and against the source
            // exactly as it was written, so the place named is a place in that
            // file rather than in something generated from it.
            $this->grammar->assertReads($written);

            // Three passes, in this order because each needs what the one
            // before it leaves. Erasure has to come first: a type argument is
            // not PHP, so nothing can parse the file until the brackets are
            // gone. Guards have to come last: placing one means knowing which
            // function a `return` sits in, which needs a tree, and the tree has
            // to be of the code as it will finally be, macros and all.
            //
            // A source with nothing in it compiles to nothing, whether it is
            // empty or holds only a newline. Left to run, the transformer would
            // open a tag over an empty file and call that a compilation.
            $transformed = trim($content) === ''
                ? ''
                : $this->guards->inject(
                    $this->transformer->transform($this->erasure->erase($content), $file)
                );

            // Before anything is written, because a file that is known not to
            // parse is worse on disk than absent: a build made of it looks
            // whole. Leaving the last good output alone is the friendlier
            // failure, and under a watch it is the only one that lets you keep
            // working while you fix the source.
            $this->syntax->assertParses($transformed, $this->openingTag->linesAddedTo($written));

            $this->ensureDirectory(dirname($outputPath));

            $this->write($outputPath, $transformed);

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

    /**
     * Writes the output, and says so when it could not.
     *
     * A write that fails and is not looked at is the worst failure this has:
     * the compile reports success, the build is empty, and the pipeline made
     * of it goes green. `-o build` where `build` is already a directory is
     * enough to do it, and that is the README's own directory example given a
     * single file.
     */
    private function write(string $path, string $contents): void
    {
        if (@file_put_contents($path, $contents) !== false) {
            return;
        }

        throw new RuntimeException(sprintf('Could not write "%s".', $path));
    }

    /**
     * Looking before creating is safe in a compile that runs once and not in a
     * watch that runs for hours, where the directory can appear or go between
     * the look and the write. What mkdir did decides, so a directory somebody
     * else made in the meantime is success, and one that cannot be made at all
     * is an error on the file rather than a warning and an empty output.
     */
    private function ensureDirectory(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (@mkdir($directory, 0755, true)) {
            return;
        }

        clearstatcache(true, $directory);

        if (!is_dir($directory)) {
            throw new RuntimeException(sprintf('Could not create the output directory "%s".', $directory));
        }
    }

    private function processDirectory(string $inputDir, string $outputDir): array
    {
        $outputDirectory = new OutputDirectory($outputDir);

        $results = [];
        foreach ((new SourceTree($inputDir))->files() as $source) {
            $results[] = $this->processFile($source->path, $outputDirectory->forSource($source->relativePath));
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
