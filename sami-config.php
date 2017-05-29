<?php
use Sami\Sami;
use Sami\RemoteRepository\GitHubRemoteRepository;
use Sami\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

$versions = GitVersionCollection::create('lib')
    ->add('1.0', '1,0 release')
    ->add('master', 'master branch')
;
return new Sami('lib', [
    'theme' => 'default',
    'versions' => $versions,
    'build_dir' => 'build/api/%version%',
    'cache_dir' => 'build/api/cache/%version%'
]);