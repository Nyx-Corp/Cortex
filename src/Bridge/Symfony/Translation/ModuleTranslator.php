<?php

namespace Cortex\Bridge\Symfony\Translation;

use Cortex\Bridge\Symfony\Module\ModuleLoader;
use Symfony\Contracts\Translation\TranslatorInterface;

class ModuleTranslator implements TranslatorInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private ModuleLoader $moduleLoader,
    ) {
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain ?? $this->moduleLoader->current, $locale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    // Déléguer toutes les autres méthodes si nécessaire
    public function __call(string $method, array $arguments)
    {
        if (method_exists($this->translator, $method)) {
            return $this->translator->$method(...$arguments);
        }

        throw new \BadMethodCallException(sprintf('Method "%s" does not exist.', $method));
    }
}
