<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\TestRunner\Commands;

use Piwik\AssetManager;
use Piwik\Config;
use Piwik\Filesystem;
use Piwik\Plugin\ConsoleCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class TestsRunUIDocker extends ConsoleCommand
{
    protected function configure()
    {
        $this->setName('tests:run-ui-docker')
            ->setDescription('Run screenshot tests in parallel using Docker')
            ->addOption('parallel', null, InputOption::VALUE_OPTIONAL, 'Number of processes to run un parallel', 2)
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum number of specs to run. If missing, runs all the specs.', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!is_dir(PIWIK_DOCUMENT_ROOT . '/tmp/tests')) {
            mkdir(PIWIK_DOCUMENT_ROOT . '/tmp/tests');
        }

        $parallelProcessesCount = $input->getOption('parallel');

        $specsToRun = $this->getSpecsToRun($input);
        $output->writeln(sprintf('<comment>%d specs to run on %d processes</comment>', count($specsToRun), $parallelProcessesCount));

        $runningProcesses = array_pad(array(), $parallelProcessesCount, null);

        $this->initContainers($runningProcesses, $output);

        while (!empty($specsToRun)) {
            foreach ($runningProcesses as $i => $process) {
                $runningProcesses[$i] = $this->startSpec($specsToRun, $i, $output);
            }

            while ($this->isSpecRunning($runningProcesses)) {
                usleep(100000);
            }
        }
    }

    private function getSpecsToRun(InputInterface $input)
    {
        $specsToRun = Filesystem::globr(PIWIK_INCLUDE_PATH . '/tests/UI/specs', '*_spec.js');

        $limit = $input->getOption('limit');
        if ($limit > 0) {
            $specsToRun = array_slice($specsToRun, 0, $limit);
        }

        return array_map(function ($file) {
            return basename($file, '_spec.js');
        }, $specsToRun);
    }

    private function initContainers($runningProcesses, OutputInterface $output)
    {
        foreach ($runningProcesses as $i => $process) {
            $output->writeln(sprintf('<comment>Initializing docker project %d</comment>', $i + 1));
            $this->runCommand($i, 'build', $output);
            $this->runCommand($i, 'up -d', $output);
        }
    }

    private function runCommand($number, $command, OutputInterface $output)
    {
        $command = $this->getDockerCommand($number, $command);
        $process = new Process($command);
        $process->setIdleTimeout(300);
        $process->setTimeout(300);
        $exitCode = $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf(
                'Error while running "%s": %s',
                $command,
                $process->getErrorOutput()
            ));
        }
    }

    private function startSpec(array &$specsToRun, $number, OutputInterface $output)
    {
        $spec = array_shift($specsToRun);
        if (!$spec) {
            return null;
        }

        $output->writeln(sprintf('Running <info>%s</info> on process %d', $spec, $number + 1));

        $logFile = 'tmp/tests/ui.' . $spec . '.log';
        $command = $this->getDockerCommand($number, 'run cli ./console tests:run-ui --keep-symlinks ' . $spec . ' > ' . $logFile . ' 2>&1');
        $process = new Process($command);
        // Somehow we need this to get the output
        $process->setTty(true);
        $process->start();

        return $process;
    }

    private function isSpecRunning(array $runningSpecs)
    {
        foreach ($runningSpecs as $process) {
            if (!$process) {
                continue;
            }

            if ($process->isRunning()) {
                return true;
            }
        }
        return false;
    }

    private function getDockerCommand($number, $command)
    {
        return sprintf(
            'docker-compose -p piwik%d %s',
            $number + 1,
            $command
        );
    }
}
