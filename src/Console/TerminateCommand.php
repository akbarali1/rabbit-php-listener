<?php

namespace Akbarali\RabbitListener\Console;

use Akbarali\RabbitListener\Managers\CacheManager;
use Illuminate\Console\Command;

class TerminateCommand extends Command
{
	
	public function __construct(
		protected CacheManager $cacheManager
	) {
		parent::__construct();
	}
	
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'rabbit:terminate';
	
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Terminate the master supervisor so it can be restarted';
	
	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function handle(): void
	{
		$processIds = $this->cacheManager->getProcessIds();
		
		if (count($processIds) === 0) {
			$this->components->error("Running processes not found");
			
			return;
		}
		
		foreach ($processIds as $processId) {
			$result = true;
			$this->components->task("Process: $processId", function () use ($processId, &$result) {
				return $result = posix_kill($processId, SIGTERM);
			});
			
			if (!$result) {
				$this->components->error("Failed to kill process: {$processId} (".posix_strerror(posix_get_last_error()).')');
				$this->cacheManager->changeProcessId($processId);
			}
		}
	}
}
