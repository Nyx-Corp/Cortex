<?php

namespace Cortex\Bridge\Symfony\Bundle\Maker\Helper;

use Symfony\Component\Finder\SplFileInfo;

/**
 * Renders .tpl.php templates using PHP's native include with extract().
 *
 * This follows Symfony MakerBundle conventions where templates are PHP files
 * that echo their output, allowing for conditionals, loops, and full PHP logic.
 *
 * Template naming convention:
 * - PHP files: {Model}.php.tpl.php → {Model}.php
 * - Twig files: index.html.twig.tpl.php → index.html.twig
 * - YAML files: routes.yaml.tpl.php → routes.yaml
 *
 * Variables are extracted into the template scope:
 * - $Model, $model (PascalCase and snake_case)
 * - $Domain, $domain
 * - $Module, $module
 * - $Action, $action
 * - $Subpath, $subpath, $subpath_namespace
 */
final class TemplateRenderer
{
    /**
     * Renders a .tpl.php template with the given variables.
     *
     * Variables are extracted into the template scope using EXTR_SKIP
     * to prevent overwriting existing variables.
     *
     * @param string               $templatePath Absolute path to the .tpl.php template
     * @param array<string, mixed> $variables    Variables to extract into template scope
     *
     * @return string The rendered template output
     */
    public function render(string $templatePath, array $variables): string
    {
        ob_start();
        try {
            extract($variables, EXTR_SKIP);
            include $templatePath;

            return ob_get_clean() ?: '';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException(sprintf('Error rendering template "%s": %s', $templatePath, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Checks if a file is a .tpl.php template.
     */
    public function isPhpTemplate(SplFileInfo $file): bool
    {
        return str_ends_with($file->getRelativePathname(), '.tpl.php');
    }

    /**
     * Gets the output filename by removing the .tpl.php suffix.
     *
     * Examples:
     * - {Model}.php.tpl.php → {Model}.php
     * - index.html.twig.tpl.php → index.html.twig
     * - routes.yaml.tpl.php → routes.yaml
     */
    public function getOutputFilename(string $templatePath): string
    {
        return preg_replace('/\.tpl\.php$/', '', $templatePath) ?? $templatePath;
    }
}
