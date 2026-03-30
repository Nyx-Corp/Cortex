<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker\Helper;

use Cortex\Bridge\Symfony\Bundle\Maker\Manipulator\PhpUpdater;
use Cortex\Bridge\Symfony\Bundle\Maker\Manipulator\YamlUpdater;
use Cortex\Component\Collection\FileCollection;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class PathCollection extends FileCollection
{
    private Filesystem $filesystem {
        get => $this->filesystem ??= new Filesystem();
    }

    private TemplateRenderer $templateRenderer {
        get => $this->templateRenderer ??= new TemplateRenderer();
    }

    public static function scan(string $baseDir): static
    {
        return parent::search(
            fn (Finder $finder) => $finder
                ->in($baseDir)
                ->ignoreDotFiles(false)
        );
    }

    /**
     * Checks if a file is a .tpl.php template.
     */
    private function isPhpTemplate(SplFileInfo $file): bool
    {
        return str_ends_with($file->getRelativePathname(), '.tpl.php');
    }

    /**
     * Transforms the destination path by removing .tpl.php suffix if present.
     */
    private function transformDestPath(string $path): string
    {
        return preg_replace('/\.tpl\.php$/', '', $path) ?? $path;
    }

    /**
     * @param array<string, string> $replacements
     */
    private function generateDestPath(string $destPath, string $destPattern, array $replacements, ?\Closure $pathTransformer = null): string
    {
        $path = str_replace(
            array_keys($replacements),
            array_values($replacements),
            sprintf('%s/%s', $destPath, $destPattern)
        );

        if ($pathTransformer) {
            $path = $pathTransformer($path);
        }

        return $this->transformDestPath($path);
    }

    /**
     * Generates Cartesian product of file variants for multi-value placeholders.
     *
     * @param array<string, list<string>> $input Variant keys mapped to possible values
     *
     * @return \Generator<array<string, string>>
     */
    private function expandTemplate(array $input): \Generator
    {
        if (empty($input)) {
            yield [];

            return;
        }

        $keys = array_keys($input);
        $firstKey = array_shift($keys);
        $firstValues = array_shift($input);

        foreach ($firstValues as $value) {
            foreach ($this->expandTemplate($input) as $product) {
                yield [$firstKey => $value] + $product;
            }
        }
    }

    /**
     * Converts replacement array to PHP variable format for .tpl.php templates.
     *
     * Transforms keys like '{Model}' to 'Model' for use with extract().
     *
     * @param array<string, string> $replacements Original replacements with braces
     *
     * @return array<string, string> Variables ready for extract()
     */
    private function toTemplateVariables(array $replacements): array
    {
        $variables = [];
        foreach ($replacements as $key => $value) {
            // Transform {Model} to Model, {model} to model, etc.
            $varName = trim($key, '{}');
            if ('' !== $varName) {
                $variables[$varName] = $value;
            }
        }

        return $variables;
    }

    /**
     * Renders file content based on template type.
     *
     * For .tpl.php files: Uses PHP include with extract() for full PHP logic support
     * For legacy files: Uses simple string replacement
     *
     * @param SplFileInfo           $file         The template file
     * @param array<string, string> $replacements Placeholder replacements
     *
     * @return string Rendered content
     */
    private function renderContent(SplFileInfo $file, array $replacements): string
    {
        if ($this->isPhpTemplate($file)) {
            $variables = $this->toTemplateVariables($replacements);

            return $this->templateRenderer->render($file->getRealPath(), $variables);
        }

        // Legacy string replacement for non-.tpl.php files
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $file->getContents()
        );
    }

    /**
     * @param array<string, string>       $replacements
     * @param array<string, list<string>> $fileVariants
     * @param \Closure|null               $pathTransformer     Optional callable to transform output paths (e.g., for subpath support)
     * @param array<string, string>       $contentReplacements Optional str_replace applied to rendered content (e.g., namespace rewriting for --root-path)
     */
    public function mirror(string $destPath, array $replacements, array $fileVariants = [], ?\Closure $pathTransformer = null, array $contentReplacements = []): self
    {
        return $this
            ->each(function (SplFileInfo $file) use ($fileVariants, $replacements) {
                $activeVariants = array_filter(
                    array_keys($fileVariants),
                    fn (string $variantKey) => str_contains($file->getRelativePathname(), $variantKey)
                );
                if (empty($activeVariants)) {
                    yield [
                        'file' => $file,
                        'dest_mirror_path' => $file->getRelativePathname(),
                        'replacements' => $replacements,
                    ];

                    return;
                }

                $variants = array_intersect_key(
                    $fileVariants,
                    array_flip($activeVariants)
                );

                foreach ($this->expandTemplate($variants) as $replacement) {
                    yield [
                        'file' => $file,
                        'dest_mirror_path' => strtr($file->getRelativePathname(), $replacement),
                        'replacements' => $replacements + $replacement,
                    ];
                }
            })
            ->map(fn (array $fileInfo) => [
                'file' => $fileInfo['file'],
                'replacements' => $fileInfo['replacements'],
                'dest_path' => $this->generateDestPath(
                    $destPath,
                    $fileInfo['dest_mirror_path'],
                    $fileInfo['replacements'],
                    $pathTransformer
                ),
            ])
            ->filter(fn (array $fileInfo) => !$this->filesystem->exists($fileInfo['dest_path']))
            ->map(function (array $fileInfo) use ($contentReplacements) {
                $destFolder = dirname($fileInfo['dest_path']);
                if (!$this->filesystem->exists($destFolder)) {
                    $this->filesystem->mkdir($destFolder, 0o755);
                }

                $content = $this->renderContent($fileInfo['file'], $fileInfo['replacements']);

                if (!empty($contentReplacements)) {
                    $content = str_replace(
                        array_keys($contentReplacements),
                        array_values($contentReplacements),
                        $content,
                    );
                }

                $this->filesystem->dumpFile($fileInfo['dest_path'], $content);

                return $fileInfo['dest_path'];
            })
        ;
    }

    public function openYaml(callable $mapper): self
    {
        return $this->map(fn (SplFileInfo $file) => new YamlUpdater($file, $this->filesystem))
            ->map($mapper)
        ;
    }

    public function openPhpClass(callable $mapper): self
    {
        return $this->map(fn (SplFileInfo $file) => new PhpUpdater($file, $this->filesystem))
            ->map($mapper)
        ;
    }

    /**
     * @return list<mixed>
     */
    public function generate(\Closure $mapper): array
    {
        return $this->map($mapper)->toArray();
    }
}
