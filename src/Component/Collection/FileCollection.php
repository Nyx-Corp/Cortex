<?php

namespace Cortex\Component\Collection;

use Symfony\Component\Finder\Finder;

class FileCollection extends AsyncCollection
{
    public static function search(\Closure $criterias): static
    {
        return parent::create($criterias(
            new Finder()->files()
                ->ignoreDotFiles(true)
                ->ignoreVCS(true)
                ->ignoreUnreadableDirs(true)
                ->sortByName()
        ));
    }
}
