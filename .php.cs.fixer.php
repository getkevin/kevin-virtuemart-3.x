<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__)
    ->name('*.php')
    ->exclude(['vendor']);

$config = new PhpCsFixer\Config();
return $config->setRules(
    [
        '@PSR12' => true,
        '@Symfony' => true,
        'visibility_required' => false,
    ]
)->setFinder($finder);
