<?php namespace Fly\Queue;

use Fly\Queue\Jobs\Job;
use Fly\Events\Dispatcher;
use Fly\Queue\Failed\FailedJobProviderInterface;

class Worker {

	/**
	 * THe queue manager instance.
	 *
	 * @var \Fly\Queue\QueueManager
	 */
	protected $manager;

	/**
	 * The failed job provider implementation.
	 *
	 * @var \Fly\Queue\Failed\FailedJobProviderInterface
	 */
	protected $failer;

	/**
	 * The event dispatcher instance.
	 *
	 * @var \Fly\Events\Dispatcher
	 */
	protected $events;

	/**
	 * Create a new queue worker.
	 *
	 * @param  \Fly\Queue\QueueManager  $manager
	 * @param  \Fly\Queue\Failed\FailedJobProviderInterface  $failer
	 * @param  \Fly\Events\Dispatcher  $events
	 * @return void
	 */
	public function __construct(QueueManager $manager,
                                FailedJobProviderInterface $failer = null,
                                Dispatcher $events = null)
	{
		$this->failer = $failer;
		$this->events = $events;
		$this->manager = $manager;
	}

	/**
	 * Listen to the given queue.
	 *
	 * @param  string  $connectionName
	 * @param  string  $queue
	 * @param  int     $delay
	 * @param  int     $memory
	 * @param  int     $sleep
	 * @param  int     $maxTries
	 * @return void
	 */
	public function pop($connectionName, $queue = null, $delay = 0, $memory = 128, $sleep = 3, $maxTries = 0)
	{
		$connection = $this->manager->connection($connectionName);

		$job = $this->getNextJob($connection, $queue);

		// If we're able to pull a job off of the stack, we will process it and
		// then make sure we are not exceeding our memory limits for the run
		// which is to protect against run-away memory leakages from here.
		if ( ! is_null($job))
		{
			$this->process(
				$this->manager->getName($connectionName), $job, $maxTries, $delay
			);
		}
		else
		{
			$this->sleep($sleep);
		}
	}

	/**
	 * Get the next job from the queue connection.
	 *
	 * @param  \Fly\Queue\Queue  $connection
	 * @param  string  $queue
	 * @return \Fly\Queue\Jobs\Job|null
	 */
	protected function getNextJob($connection, $queue)
	{
		if (is_null($queue)) return $connection->pop();

		foreach (explode(',', $queue) as $queue)
		{
			if ( ! is_null($job = $connection->pop($queue))) return $job;
		}
	}

	/**
	 * Process a given job from the queue.
	 *
	 * @param  string  $connection
	 * @param  \Fly\Queue\Jobs\Job  $job
	 * @param  int  $maxTries
	 * @param  int  $delay
	 * @return void
	 *
	 * @throws \Exception
	 */
	public function process($connection, Job $job, $maxTries = 0, $delay = 0)
	{
		if ($maxTries > 0 && $job->attempts() > $maxTries)
		{
			return $this->logFailedJob($connection, $job);
		}

		try
		{
			// First we will fire off the job. Once it is done we will see if it will
			// be auto-deleted after processing and if so we will go ahead and run
			// the delete method on the job. Otherwise we will just keep moving.
			$job->fire();

			if ($job->autoDelete()) $job->delete();
		}

		catch (\Exception $e)
		{
			// If we catch an exception, we will attempt to release the job back onto
			// the queue so it is not lost. This will let is be retried at a later
			// time by another listener (or the same one). We will do that here.
			if ( ! $job->isDeleted()) $job->release($delay);

			throw $e;
		}
	}

	/**
	 * Log a failed job into storage.
	 *
	 * @param  string  $connection
	 * @param  \Fly\Queue\Jobs\Job  $job
	 * @return void
	 */
	protected function logFailedJob($connection, Job $job)
	{
		if ($this->failer)
		{
			$this->failer->log($connection, $job->getQueue(), $job->getRawBody());

			$job->delete();

			$this->raiseFailedJobEvent($connection, $job);
		}
	}

	/**
	 * Raise the failed queue job event.
	 *
	 * @param  string  $connection
	 * @param  \Fly\Queue\Jobs\Job  $job
	 * @return void
	 */
	protected function raiseFailedJobEvent($connection, Job $job)
	{
		if ($this->events)
		{
			$this->events->fire('flyphp.queue.failed', array($connection, $job));
		}
	}

	/**
	 * Sleep the script for a given number of seconds.
	 *
	 * @param  int   $seconds
	 * @return void
	 */
	public function sleep($seconds)
	{
		sleep($seconds);
	}

	/**
	 * Get the queue manager instance.
	 *
	 * @return \Fly\Queue\QueueManager
	 */
	public function getManager()
	{
		return $this->manager;
	}

	/**
	 * Set the queue manager instance.
	 *
	 * @param  \Fly\Queue\QueueManager  $manager
	 * @return void
	 */
	public function setManager(QueueManager $manager)
	{
		$this->manager = $manager;
	}

}