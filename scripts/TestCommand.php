<?php

namespace ActiveRecord\Scripts;

use Composer\Script\Event;
use Symfony\Component\Process\Process;

class TestCommand
{
    public static function runTest(Event $event)
    {
        try {
            $args = $event->getArguments();

            $fileName = count($args) >= 1 ? $args[0] : null;
            $filter = count($args) >= 2 ? $args[1] : null;

            if (null !== $fileName && !str_starts_with($fileName, '--')) {
                $args = TestCommand::buildArgs($fileName, $filter);
                $str = 'Running: ' . implode(' ', $args) . "\n";
                if (1 === count($args)) {
                    $str .= "To run just the tests in test/CallbackTest.php, try: composer test callback\n";
                }
                if (1 === count($args) || 2 === count($args)) {
                    $str .= "To run a specific test in test/DateTimeTest.php, try: composer test dateTime testSetIsoDate\n";
                }
                echo $str;
            } else {
                array_unshift($args, 'vendor/bin/phpunit');
            }

            $process = new Process($args);
            $process->setTimeout(1200);

            if (Process::isTtySupported()) {
                $process->setTty(true);
                $process->run();
            } else {
                $process->run(function ($type, $buffer): void {
                    echo $buffer;
                });
            }

            return 0;
        } catch (\Exception $e) {
            echo "\n" . $e->getMessage() . "\n";
        }
    }

    private static function buildArgs(string|null $fileName, string|null $filter): array
    {
        $args = ['vendor/bin/phpunit'];

        if (null === $fileName) {
            return $args;
        }

        if (str_starts_with($fileName, 'test/')) {
            $fileName = substr($fileName, strlen('test/'));
        }
        if (str_ends_with($fileName, '.php')) {
            $fileName = substr($fileName, 0, -strlen('.php'));
        }
        if (str_ends_with($fileName, 'Test')) {
            $fileName = substr($fileName, 0, -strlen('Test'));
        }
        $fileName = ucfirst($fileName);
        $fileName = "test/{$fileName}Test.php";
        if (!file_exists($fileName)) {
            throw new \Exception("{$fileName} does not exist. Did you mispell it?");
        }

        if (null != $filter) {
            array_push($args, '--filter');
            array_push($args, $filter);
        }
        array_push($args, $fileName);

        return $args;
    }
}
