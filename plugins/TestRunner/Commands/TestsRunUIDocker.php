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
            ->addOption('parallel', null, InputOption::VALUE_OPTIONAL, 'Number of processes to run un parallel', 2);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $specsToRun = Filesystem::globr(PIWIK_INCLUDE_PATH . '/tests/UI/specs', '*_spec.js');
        $specsToRun = array_map(function ($file) {
            return basename($file, '_spec.js');
        }, $specsToRun);

        $output->writeln(sprintf('<comment>%d specs to run</comment>', count($specsToRun)));

        $runningProcesses = array_pad(array(), $input->getOption('parallel'), null);

        $testsOutput = '';

        while (!empty($specsToRun)) {
            foreach ($runningProcesses as $i => $process) {
                $runningProcesses[$i] = $this->startSpec($specsToRun, $i, $output);
            }

            while ($this->isSpecRunning($runningProcesses)) {
                usleep(100);
            }

            $testsOutput .= '---' . PHP_EOL;
            foreach ($runningProcesses as $process) {
                if (!$process) {
                    continue;
                }

                $testsOutput .= $process->getOutput();
            }
        }

        $output->writeln($testsOutput);
    }

    private function startSpec(array &$specsToRun, $number, OutputInterface $output)
    {
        $spec = array_shift($specsToRun);
        if (!$spec) {
            return null;
        }

        $output->writeln(sprintf('Running <info>%s</info> on process %d', $spec, $number));

        $command = sprintf(
            'docker-compose –project-name %s run cli ./console tests:run-ui %s',
            'piwik_' . $number,
            $spec
        );

        $process = new Process($command);
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
}
