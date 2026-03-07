<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__.'/src')
    ->exclude('Bridge/Symfony/Bundle/Resources/maker');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        '@PHP84Migration' => true,
    ])
    ->setFinder($finder);
