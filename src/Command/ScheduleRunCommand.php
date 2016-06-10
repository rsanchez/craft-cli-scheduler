<?php

namespace CraftCli\Scheduler\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Output\OutputInterface;
use Crunz\Console\Command\ScheduleRunCommand as BaseCommand;
use Crunz\Schedule;
use Crunz\Invoker;
use Crunz\Configuration;
use ReflectionProperty;

class ScheduleRunCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('schedule:run');
        $this->setDescription('Run the cron scheduler.');
        $configuration = Configuration::getInstance();
        $this->setConfiguration($configuration);

        // crunz errors if i don't do this
        $reflectionProperty = new ReflectionProperty($configuration, 'parameters');
        $reflectionProperty->setAccessible(true);
        $parameters = $reflectionProperty->getValue($configuration);
        $parameters['log_output'] = true;
        $parameters['output_log_file'] = '/dev/null';
        $reflectionProperty->setValue($configuration, $parameters);
        $reflectionProperty->setAccessible(false);
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $taskDir = $this->getApplication()->getConfigItem('taskDir');

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
    public function runTasks($tasks = array())
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
    public function collectTaskFiles($ignore)
    {
        $dir = $this->getApplication()->getConfigItem('taskDir');
        $namespace = $this->getApplication()->getConfigItem('taskNamespace');

        return $this->getApplication()->findClassInDir('\\CraftCli\\Scheduler\\TaskInterface', $dir, $namespace);
    }
}
