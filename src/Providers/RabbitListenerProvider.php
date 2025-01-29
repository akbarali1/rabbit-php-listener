<?php
declare(strict_types=1);

namespace Akbarali\RabbitListener\Providers;

use Akbarali\RabbitListener\Console\ContinueCommand;
use Akbarali\RabbitListener\Console\InfoCommand;
use Akbarali\RabbitListener\Console\InstallCommand;
use Akbarali\RabbitListener\Console\PauseCommand;
use Akbarali\RabbitListener\Console\RabbitChannelListener;
use Akbarali\RabbitListener\Console\TerminateCommand;
use Illuminate\Contracts\Foundation\CachesRoutes;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class RabbitListenerProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot(): void
	{
		$this->registerCommands();
		$this->offerPublishing();
		$this->registerRoutes();
	}
	
	protected function registerRoutes(): void
	{
		if ($this->app instanceof CachesRoutes && $this->app->routesAreCached()) {
			return;
		}
		
		Route::group([
			'domain'     => config('rabbit.domain'),
			'prefix'     => config('rabbit.path'),
			'middleware' => config('rabbit.middleware', 'web'),
		], function () {
			$this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
		});
	}
	
	protected function registerCommands(): void
	{
		if ($this->app->runningInConsole()) {
			$this->commands([
				InstallCommand::class,
				RabbitChannelListener::class,
				TerminateCommand::class,
				PauseCommand::class,
				ContinueCommand::class,
				InfoCommand::class,
			]);
		}
	}
	
	protected function offerPublishing(): void
	{
		if ($this->app->runningInConsole()) {
			$this->publishes([
				__DIR__.'/../../config/rabbit.php' => config_path('rabbit.php'),
			], 'rabbit-config');
			
			$this->publishes([
				__DIR__.'/../stubs/UserExceptionCode.stub' => app_path('Enums/UserExceptionCode.php'),
			], 'exception-code');
			
			$this->publishes([
				__DIR__.'/../../lang/eng/exceptions.php' => lang_path('eng/exceptions.php'),
			], 'rabbit-lang');
			
			$this->publishes([
				__DIR__.'/../../routes/rabbit.php' => base_path('routes/rabbit.php'),
			], 'rabbit-route');
		}
	}
	
	public function register(): void
	{
		if (!defined('RABBIT_LISTENER_PATH')) {
			define('RABBIT_LISTENER_PATH', dirname(__DIR__).'/');
		}
		
		$this->mergeConfigFrom(
			path: __DIR__.'/../../config/rabbit.php',
			key : 'rabbit'
		);
	}
	
}
