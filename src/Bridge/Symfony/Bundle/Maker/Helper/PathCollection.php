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

    public static function scan(string $baseDir): static
    {
        return parent::search(
            fn (Finder $finder) => $finder
                ->in($baseDir)
                ->ignoreDotFiles(false)
        );
    }

    private function generateDestPath(string $destPath, string $destPattern, array $replacements)
    {
        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            sprintf('%s/%s', $destPath, $destPattern)
        );
    }

    private function expandTemplate(array $input)
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

    public function mirror(string $destPath, array $replacements, array $fileVariants = []): self
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
                    $fileInfo['replacements']
                ),
            ])
            ->filter(fn (array $fileInfo) => !$this->filesystem->exists($fileInfo['dest_path']))
            ->map(function (array $fileInfo) {
                $destFolder = dirname($fileInfo['dest_path']);
                if (!$this->filesystem->exists($destFolder)) {
                    $this->filesystem->mkdir($destFolder, 0755);
                }

                $this->filesystem->dumpFile($fileInfo['dest_path'], str_replace(
                    array_keys($fileInfo['replacements']),
                    array_values($fileInfo['replacements']),
                    $fileInfo['file']->getContents()
                ));

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

    public function generate(\Closure $mapper): array
    {
        return $this->map($mapper)->toArray();
    }
}
