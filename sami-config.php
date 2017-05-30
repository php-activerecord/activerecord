<?php

use Sami\Sami;
use Sami\Version\GitVersionCollection;

$versions = GitVersionCollection::create('lib')
    ->addFromTags('v1.0')
    ->add('master', 'master branch')
;

return new Sami('lib', [
    'title' => 'php-activerecord API',
    'theme' => 'php-ar',
    'versions' => $versions,
    'build_dir' => 'build/api/%version%',
    'cache_dir' => 'build/api/cache/%version%',
    'template_dirs' => [
        'docs/sami/themes'
    ]
]);

// for local dev

//return new Sami('lib', [
//    'title' => 'php-activerecord API',
//    'usage_docs_title' => 'Usage',
//    'theme' => 'php-ar',
//    'build_dir' => 'build/api',
//    'cache_dir' => 'build/api/cache',
//    'template_dirs' => [
//        'docs/sami/themes'
//    ]
//]);
