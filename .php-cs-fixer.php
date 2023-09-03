<?php

$finder = new PhpCsFixer\Finder();
$config = new PhpCsFixer\Config('json-schema', 'json-schema style guide');
$finder->in(__DIR__)
    ->exclude('.github')
    ->exclude('.phpunit.cache')
    ->exclude('.stan.cache')
    ->exclude('.vscode')
    ->exclude('assets')
    ->exclude('docs')
    ->exclude('node_modules')
    ->exclude('output')
    ->exclude('vendor');

$config
    ->setRules([
        // default
        '@PSR2' => true,
        '@Symfony' => true,
        // additionally
        'array_syntax' => ['syntax' => 'short'],
        'binary_operator_spaces' => false,
        'concat_space' => ['spacing' => 'one'],
        'no_unused_imports' => true,
        'no_useless_else' => true,
        'no_useless_return' => true,
        'ordered_imports' => true,
        'phpdoc_no_package' => false,
        'phpdoc_order' => true,
        'phpdoc_summary' => false,
        'trailing_comma_in_multiline' => false,
        'simplified_null_return' => false,
    ])
    ->setFinder($finder);

return $config;
