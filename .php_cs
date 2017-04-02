<?php
// vim: set ft=php:

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
;

return PhpCsFixer\Config::create()
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => true,
    ])
    ->setFinder($finder)
;
