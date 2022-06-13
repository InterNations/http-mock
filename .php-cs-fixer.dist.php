<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'combine_consecutive_unsets' => true,
        'concat_space' => ['spacing' => 'one'],
        'return_type_declaration' => ['space_before' => 'one'],
        'no_unreachable_default_argument_value' => false,
        'yoda_style' => false,
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'increment_style' => ['style' => 'post']
    ])
    ->setFinder($finder);
