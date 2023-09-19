<?php

namespace test;

use _PHPStan_a4fa95a42\Symfony\Component\Console\Application;
use PHPStan\Command\AnalyseCommand;

class ActiveRecordPHPStanTest extends \DatabaseTestCase
{
    /**
     * This test is a temporary hack until I can figure out how to properly
     * debug composer scripts.
     */
    public function testPhpstan()
    {
        $this->expectNotToPerformAssertions();
        //        $version = 'Version unknown';
        //        try {
        //            $version = \Jean85\PrettyVersions::getVersion(
        //                'phpstan/phpstan'
        //            )->getPrettyVersion();
        //        } catch (\OutOfBoundsException $e) {
        //        }
        //
        //        $application = new Application(
        //            'PHPStan - PHP Static Analysis Tool',
        //            $version
        //        );
        //        $application->setAutoExit(false);
        //        $application->add(new AnalyseCommand([]));
        //        $application->run(
        //            new \_PHPStan_a4fa95a42\Symfony\Component\Console\Input\ArgvInput(
        //                [
        //                    'vendor/phpstan/phpstan/bin/phpstan',
        //                    'analyse',
        //                    '--xdebug',
        //                    '--debug',
        //                    '-c',
        //                    './phpstan.neon.dist',
        //                    'test/phpstan',
        //                ]
        //            )
        //        );
    }
}
