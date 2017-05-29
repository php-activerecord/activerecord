<?php
use Sami\Sami;
use Sami\RemoteRepository\GitHubRemoteRepository;
use Sami\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

$versions = GitVersionCollection::create('lib')
    ->add('generate-docs', 'docs branch')
;
return new Sami('lib', [
    'theme' => 'default',
    'versions' => $versions,
    'build_dir' => 'build/api/%version%',
    'cache_dir' => 'build/api/cache/%version%'
]);