<?php

namespace Cortex\Component\Model\Factory\Mapper;

use Cortex\Component\Model\Factory\ModelPrototype;
use Cortex\ValueObject\RegisteredClass;
use Symfony\Component\String\UnicodeString;

class FlatModelMapper implements ModelMapper
{
    private array $keyMap = [];
    private array $ignoredKeys = [];
    private array $propertyKeys = [];
    private ?\ReflectionClass $reflection = null;

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
            if (isset($this->propertyKeys[$attribute])) {
                $propName = $this->propertyKeys[$attribute];
                $prototype->callbacks->set($propName, fn ($model) => $model->$propName = $value);
                continue;
            }

            $uAttribute = new UnicodeString($attribute);
            $triedKeys = [
                $attribute,
                (string) $uAttribute->camel(),
                (string) $uAttribute->snake(),
            ];
            foreach ($triedKeys as $key) {
                if ($prototype->constructors->has($key)) {
                    $declaredKey = $this->resolveDeclaredKey($prototype, $key);
                    $this->keyMap[$attribute] = $declaredKey;
                    $prototype->constructors->set($declaredKey, $value);
                    continue 2;
                }
            }

            $propertyName = $this->resolveSettableProperty($prototype, $attribute);
            if ($propertyName) {
                $this->propertyKeys[$attribute] = $propertyName;
                $prototype->callbacks->set($propertyName, fn ($model) => $model->$propertyName = $value);
                continue;
            }

            $this->ignoredKeys[$attribute] = true;
        }

        $prototype->modelClass = $this->guessModelClass($prototype, $modelData);

        return $prototype;
    }

    private function resolveDeclaredKey(ModelPrototype $prototype, string $key): string
    {
        $uKey = new UnicodeString($key);
        $variants = [$key, (string) $uKey->camel(), (string) $uKey->snake()];

        foreach ($prototype->constructors->declaredKeys() as $declared) {
            if (\in_array($declared, $variants, true)) {
                return $declared;
            }
        }

        return $key;
    }

    private function resolveSettableProperty(ModelPrototype $prototype, string $attribute): ?string
    {
        $this->reflection ??= new \ReflectionClass($prototype->modelClass->value);
        $uAttribute = new UnicodeString($attribute);
        $variants = [$attribute, (string) $uAttribute->camel(), (string) $uAttribute->snake()];

        foreach ($variants as $name) {
            try {
                $prop = $this->reflection->getProperty($name);
            } catch (\ReflectionException) {
                continue;
            }
            if ($prop->isPublic() && !$prop->isReadOnly() && !$prop->isVirtual() && !$prop->isPrivateSet() && !$prop->isProtectedSet()) {
                return $name;
            }
        }

        return null;
    }
}
