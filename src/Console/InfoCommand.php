<?php

namespace Akbarali\RabbitListener\Console;

use Akbarali\RabbitListener\Managers\CacheManager;
use Illuminate\Console\Command;

class InfoCommand extends Command
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
	protected $signature = 'rabbit:info';
	
	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Terminate the master supervisor so it can be pause';
	
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
		
		$totalVmSize = 0;
		$totalVmRss  = 0;
		
		foreach ($processIds as $key => $pid) {
			// Fayllar yo'llari
			$statusFile = "/proc/$pid/status";
			// Xotira iste'moli ma'lumotlarini olish
			if (!file_exists($statusFile)) {
				continue;
			}
			// /proc/[pid]/status faylini o'qish
			$status = file_get_contents($statusFile);
			$lines  = explode("\n", $status);
			
			// VmSize va VmRSS qiymatlarini topish
			$vmSize = 0;
			$vmRss  = 0;
			
			foreach ($lines as $line) {
				if (str_contains($line, 'VmSize')) {
					preg_match('/VmSize:\s+(\d+)\s+kB/', $line, $matches);
					if (isset($matches[1])) {
						$vmSize = $matches[1];
					}
				}
				if (str_contains($line, 'VmRSS')) {
					preg_match('/VmRSS:\s+(\d+)\s+kB/', $line, $matches);
					if (isset($matches[1])) {
						$vmRss = $matches[1];
					}
				}
			}
			
			// Xotira iste'molining KB va MB da chiqarilishi
			$vmSizeMB = round($vmSize / 1024, 2);
			$vmRssMB  = round($vmRss / 1024, 2);
			
			$this->components->twoColumnDetail("<fg=green;options=bold>PID:</>", "$pid\n");
			$this->components->twoColumnDetail("<fg=green;options=bold>Umumiy xotira iste'moli (VmSize):</>", "$vmSize kB ($vmSizeMB MB)\n");
			$this->components->twoColumnDetail("<fg=green;options=bold>Haqiqiy xotira iste'moli (VmRSS):</>", "$vmRss kB ($vmRssMB MB)\n");
			if ($key !== array_key_last($processIds)) {
				$this->components->twoColumnDetail("<fg=green;options=bold></>", "\n");
			}
			
			$totalVmRss  += $vmRss;
			$totalVmSize += $vmSize;
		}
		
		if (count($processIds) > 1) {
			$total = count($processIds);
			// Xotira iste'molining KB va MB da chiqarilishi
			$totalVmSizeMB = round($totalVmSize / 1024, 2);
			$totalVmRssMB  = round($totalVmRss / 1024, 2);
			$this->components->twoColumnDetail("<fg=green;options=bold></>", "\n");
			$this->components->twoColumnDetail("<fg=green;options=bold>Total process count:</>", "{$total}\n");
			$this->components->twoColumnDetail("<fg=green;options=bold>Jami umumiy xotira iste'moli (VmSize):</>", "$totalVmSize kB ($totalVmSizeMB MB)\n");
			$this->components->twoColumnDetail("<fg=green;options=bold>Jami haqiqiy xotira iste'moli (VmRSS):</>", "$totalVmRss kB ($totalVmRssMB MB)\n");
		}
		
	}
}
