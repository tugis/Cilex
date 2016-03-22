<?php

namespace Cilex\Command;

use JenkinsApi\Jenkins;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Cilex\Provider\Console\Command;
use Symfony\Component\Console\Helper\Table;
use \PDO;

/**
 * Command to get Jenkins jobs and store jobs on a sqlite DB.
 */
class JenkinsCommand extends Command
{
    CONST DEFAULT_SQLITE_DB_NAME = 'jobs.sqlite';

    CONST SQLITE_CREATE_TABLE_SQL = "CREATE TABLE IF NOT EXISTS jobs
                      (id INTEGER PRIMARY KEY, name TEXT, status TEXT, checked_at INTEGER )";

    CONST SQLITE_INSERT_JOB_SQL = "INSERT INTO jobs (name,status,checked_at) values (?, ? ,?)";

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('get:jobs')
            ->setDescription('Get the list of Jenkins jobs created')
            ->addArgument('jenkins_url', InputArgument::REQUIRED, 'Jenkins URL you want to monitor')
            ->addArgument('sqlite_name', InputArgument::OPTIONAL,
                'Choose the sqlite db name',
                $this::DEFAULT_SQLITE_DB_NAME);
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jenkins_url = $input->getArgument('jenkins_url');
        $sqlite_name = $input->getArgument('sqlite_name');

        try {
            $jobs = $this->getJobs($jenkins_url);
        } catch (\Exception $ex) {
            $output->writeln(
                "<error>It was not possible to access the given Jenkins instance.</error> "
            );
            $output->writeln($ex->getMessage());
            exit;
        }

        if ($jobs) {
            $this->printCurrentlyConfiguredJobs($output, $jobs);

            try {
                $this->storeRun($jobs, $sqlite_name);
                $output->writeln(
                    sprintf(
                        "<info>Note: We stored on %s db the currently configured jobs</info>",
                        $sqlite_name
                    )
                );
            } catch (\Exception $ex) {
                $output->writeln("<error>It was not possible to store this run.</error>");
                $output->writeln($ex->getMessage());
            }
        } else {
            $output->writeln("<error>There are no configured jobs for the given Jenkins instance</error>");
        }
    }

    /**
     * Get a list of the current jobs created on the given Jenkins instance
     * @param $jenkins_url string Jenkins server URL
     * @return \JenkinsApi\Item\Job[]
     */
    protected function getJobs($jenkins_url) {
        $jenkins = new Jenkins($jenkins_url);
        return $jenkins->getJobs();
    }

    /**
     * Stores this run on sqlite database
     * @param $jobs array Jobs currently configured
     * @param $sqlite_name string The name of the sqlite db
     * @return \JenkinsApi\Item\Job[]
     */
    protected function storeRun($jobs, $sqlite_name) {
        $db = new PDO('sqlite:'.$sqlite_name);
        $db->exec($this::SQLITE_CREATE_TABLE_SQL);

        foreach ($jobs as $job) {
            $query = $db->prepare(
                $this::SQLITE_INSERT_JOB_SQL
            );
            $query->execute(
                array(
                    $job->getName(),
                    $job->getColor(),
                    time()
                )
            );
        }
    }

    /**
     * @param $output OutputInterface The output interface
     * @param $jobs array The currently configured jobs
     */
    protected function printCurrentlyConfiguredJobs($output, $jobs) {
        $output->writeln("");
        $output->writeln("<info>List of currently configured jobs:</info>");
        $table = new Table($output);
        $table->setHeaders(array('Name', 'Status'));
        foreach ($jobs as $job) {
            $table->addRow(array(
                    $job->getName(),
                    $job->getColor()
                )
            );;
        }
        $table->render();
        $output->writeln("");
    }
}
