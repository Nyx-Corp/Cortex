<?php

namespace Cortex\Component\Model\Factory\Mapper;

use Cortex\Component\Model\Factory\ModelPrototype;
use Cortex\ValueObject\RegisteredClass;
use Symfony\Component\String\UnicodeString;

class FlatModelMapper implements ModelMapper
{
    private array $keyMap = [];
    private array $ignoredKeys = [];

    protected string $defaultKey = '_default';

    protected function guessModelClass(ModelPrototype $prototype, array $modelData): RegisteredClass
    {
        return $prototype->modelClass;
    }

    public function prototype(ModelPrototype $prototype, array $modelData): ?ModelPrototype
    {
        // auto camel <-> snake mapping
        foreach ($modelData[$this->defaultKey] ?? [] as $attribute => $value) {
            if (isset($this->ignoredKeys[$attribute])) {
                continue;
            }
            if (isset($this->keyMap[$attribute])) {
                $prototype->constructors->set($this->keyMap[$attribute], $value);
                continue;
            }

            $uAttribute = new UnicodeString($attribute);
            $triedKeys = [
                $attribute,
                $uAttribute->camel(),
                $uAttribute->snake(),
            ];
            foreach ($triedKeys as $key) {
                if ($prototype->constructors->has($attribute)) {
                    $this->keyMap[$attribute] = $attribute;
                    $prototype->constructors->set($attribute, $value);
                    continue 2;
                }
            }

            $this->ignoredKeys[$attribute] = true;
        }

        $prototype->modelClass = $this->guessModelClass($prototype, $modelData);

        return $prototype;
    }
}
