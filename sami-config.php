<?php
use Sami\Sami;
use Sami\RemoteRepository\GitHubRemoteRepository;
use Sami\Version\GitVersionCollection;
use Symfony\Component\Finder\Finder;

// generate documentation for all v2.0.* tags, the 2.0 branch, and the master one
$versions = GitVersionCollection::create('lib')
    ->addFromTags('v1.0.*')
    ->add('master', 'master branch')
;

return new Sami('lib', [
    'theme' => 'symfony',
    'versions' => $versions,
    'build_dir' => 'docs/build/%version%',
    'cache_dir' => 'docs/cache/%version%'
]);
