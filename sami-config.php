<?php
use Sami\Sami;
use Sami\RemoteRepository\GitHubRemoteRepository;
use Sami\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

// generate documentation for all v2.0.* tags, the 2.0 branch, and the master one
$versions = GitVersionCollection::create('lib')
    ->add('generate-docs', 'docs branch')
;

echo "DIR **** " . __DIR__;

return new Sami('lib', [
    'theme' => 'php-ar',
    'versions' => $versions,
    'build_dir' => 'docs/build/%version%',
    'cache_dir' => 'docs/cache/%version%',
    'template_dirs' => [
        __DIR__.'/docs/theme'
    ]
]);
