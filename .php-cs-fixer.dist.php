<?php

$finder = (new PhpCsFixer\Finder())
    ->files()
    ->in([
        __DIR__.'/bin',
        __DIR__.'/config',
        __DIR__.'/migrations',
        __DIR__.'/src',
        __DIR__.'/tests',
    ])
    ->name('*.php')
    ->notPath('config/bundles.php')
    ->notPath('config/preload.php')
;

return (new PhpCsFixer\Config())
    ->setCacheFile(__DIR__.'/var/cache/.php-cs-fixer.cache')
    ->setRiskyAllowed(false)
    ->setRules([
        '@Symfony' => true,
        'global_namespace_import' => false,
    ])
    ->setFinder($finder)
;
