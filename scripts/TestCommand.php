<?php

namespace ActiveRecord\Scripts;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class TestCommand extends Command
{
    protected function configure(): void
    {
        $this->setDefinition([
            new InputArgument('fileName', InputArgument::OPTIONAL),
            new InputArgument('filter', InputArgument::OPTIONAL),
        ]);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $args = $this->buildArgs($input->getArgument('fileName'), $input->getArgument('filter'));

            $str = "Running: " . implode(' ', $args) . "\n";
            if (count($args) === 1) {
                $str .= "To run just the tests in test/CallbackTest.php, try: composer test callback\n";
            }
            if (count($args) === 1 || count($args) === 2) {
                $str .= "To run a specific test in test/DateTimeTest.php, try: composer test dateTime testSetIsoDate \n";
            }
            $output->writeln($str);

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
            $output->writeln("\n".$e->getMessage());

            return 1;
        }
    }

    private function buildArgs(string|null $fileName, string|null $filter): array
    {
        $args = ['vendor/bin/phpunit'];

        if (null === $fileName) {
            return $args;
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
