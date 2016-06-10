<?php

namespace CraftCli\Scheduler\Command;

use Symfony\Component\Console\ {
    Input\InputInterface,
    Input\InputOption,
    Input\InputArgument,
    Input\InputDefinition,
    Output\OutputInterface
};
use Crunz\Console\Command\ScheduleRunCommand as BaseCommand;
use Crunz\Schedule;
use Crunz\Invoker;
use Crunz\Configuration;

class ScheduleRunCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('schedule:run');
        $this->setDescription('Run the cron scheduler.');
        $this->setConfiguration(Configuration::getInstance());
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $taskDir = $this->application->getConfigItem('taskDir');

        if (!$taskDir) {
            $output->writeln('<error>Missing taskDir in Craft CLI config.</error>');

            return;
        }

        $input->bind(new InputDefinition([
            new InputArgument('source', InputArgument::OPTIONAL, 'The source directory to collect the tasks.', $taskDir)
        ]));

        return parent::execute($input, $output);
    }

    /**
     * {@inheritdoc}
     */
    public function runTasks($tasks = [])
    {
        foreach ($tasks as $class) {
            $task = new $class();

            $schedule = $task->schedule();

            $this->addRunningEventsFromSchedule($schedule);
        }
    }

    /**
     * Add running events from schedule
     * @param \Crunz\Schedule $schedule
     */
    protected function addRunningEventsFromSchedule(Schedule $schedule)
    {
        $events = $schedule->dueEvents(new Invoker());

        foreach ($events as $event) {
            // Running pre-execution hooks and the event itself
            $this->runningEvents[] = $event->callBeforeCallbacks(new Invoker())
                                      ->run(new Invoker());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function collectTaskFiles($dir)
    {
        $namespace = $this->application->getConfigItem('taskNamespace');

        return $this->application->findClassInDir('\\CraftCli\\Scheduler\\TaskInterface', $dir, $namespace);
    }
}
