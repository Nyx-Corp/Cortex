<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker\Helper;

final class PhpVar
{
    private const NO_DEFAULT = '@@--no_default--@@';

    public readonly bool $hasDefault;
    public readonly mixed $default;

    public string $type {
        get => $this->type ??= basename(str_replace('\\', '/', $this->fqcn));
    }

    public function __construct(
        public readonly string $fqcn, // 'string', 'int', 'bool', etc.
        public readonly ?string $name = null,
        public readonly string $doc = '',
        mixed $default = self::NO_DEFAULT, // Valeur par défaut, si applicable
    ) {
        $this->hasDefault = self::NO_DEFAULT !== $default;
        $this->default = $default;
    }

    public function isScalar(): bool
    {
        return in_array($this->fqcn, ['string', 'int', 'bool', 'float', 'array', 'null']);
    }
}
