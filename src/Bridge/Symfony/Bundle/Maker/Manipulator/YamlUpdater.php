<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker\Manipulator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Yaml;

final class YamlUpdater
{
    private array $configData;

    public function __construct(
        private SplFileInfo $config,
        private Filesystem $filesystem,
    ) {
        if (!$this->config->isReadable()) {
            throw new \InvalidArgumentException(sprintf('The provided config "%s" is not readable.', $this->config->getPathname()));
        }

        $this->configData = Yaml::parseFile($this->config->getPathname(), Yaml::PARSE_CUSTOM_TAGS | Yaml::PARSE_CONSTANT | Yaml::PARSE_DATETIME) ?? [];
    }

    public function addEntry(string $entryName, array $entryConfig, ?string $beforeKey = null): self
    {
        // Ne rien faire si déjà présent
        if (isset($this->configData[$entryName])) {
            return $this;
        }

        $keys = array_keys($this->configData);

        if (null === $beforeKey || !in_array($beforeKey, $keys, true)) {
            array_unshift($keys, $entryName);
        } else {
            $beforeKeyIndex = array_search($beforeKey, $keys, true);

            $keys = array_merge(
                array_slice($keys, 0, $beforeKeyIndex),
                [$entryName],
                array_slice($keys, $beforeKeyIndex, count($keys) - $beforeKeyIndex)
            );
        }

        $newData = [];

        foreach ($keys as $key) {
            $newData[$key] = $key === $entryName ? $entryConfig : $this->configData[$key];
        }

        $this->configData = $newData;

        return $this;
    }

    public function save(): SplFileInfo
    {
        $this->filesystem->dumpFile(
            $this->config->getPathname(),
            Yaml::dump($this->configData, 4, 4)
        );

        return $this->config;
    }
}
