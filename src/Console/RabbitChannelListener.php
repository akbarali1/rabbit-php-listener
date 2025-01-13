<?php

namespace Akbarali\RabbitListener\Console;

use Akbarali\RabbitListener\Dispatchers\RabbitMQChannelDispatcher;
use Akbarali\RabbitListener\Exceptions\InternalException;
use Akbarali\RabbitListener\Exceptions\RabbitException;
use Akbarali\RabbitListener\Managers\CacheManager;
use Akbarali\RabbitListener\Presenters\RabbitApiResponse;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Throwable;

class RabbitChannelListener extends Command
{
	#region Properties
	protected $signature   = 'rabbit:channel:queue {queue}';
	protected $description = 'Start to listening RabbitMQ';
	
	protected AMQPStreamConnection $connection;
	protected AMQPChannel          $channel;
	protected string               $queueName;
	protected string               $connectionName;
	protected bool                 $working = true;
	
	protected array      $routes;
	protected Collection $availableLocales;
	
	#endregion
	
	/**
	 * @throws Exception
	 */
	public function __construct(
		protected CacheManager $cacheManager
	) {
		$config                 = config('rabbit', []);
		$this->connection       = app()->isLocal() ? null : new AMQPStreamConnection(
			$config['connection']['host'],
			$config['connection']['port'],
			$config['connection']['user'],
			$config['connection']['password'],
			$config['connection']['vhost']
		);
		$this->availableLocales = collect($config['available_locales'] ?? []);
		
		parent::__construct();
	}
	
	/**
	 * @throws Exception
	 */
	public function handle(): void
	{
		$this->cacheManager->setCache(getmypid());
		// флаг остановки
		$shallStopWorking = false;
		$this->listenForSignals($shallStopWorking);
		
		$this->queueName = $this->argument('queue');
		$this->info("rabbit:channel:listen $this->queueName -- started");
		
		try {
			$this->checkConnection();
			$this->connect();
			
			while ($this->channel->is_open() && !$shallStopWorking) {
				pcntl_signal_dispatch();
				if ($this->working) {
					$this->channel->wait();
				}
			}
			$this->info("rabbit:channel:listen $this->queueName -- end");
		} catch (Throwable $exception) {
			Log::error($exception);
			$this->error($exception->getMessage());
			$this->info("rabbit:channel:listen $this->queueName -- error: {$exception->getMessage()}");
		} finally {
			$this->closeConnection();
			$this->cacheManager->forgetCache(getmypid());
		}
	}
	
	/**
	 * @throws Exception
	 */
	protected function checkConnection(): void
	{
		if (!$this->connection->isConnected()) {
			$this->connection->reconnect();
		}
	}
	
	protected function listenForSignals(bool &$shallStopWorking): void
	{
		$stop = static fn() => $shallStopWorking = true;
		// сигнал об остановке от supervisord
		pcntl_signal(SIGTERM, $stop);
		// Close Terminal
		pcntl_signal(SIGHUP, $stop);
		// обработчик для ctrl+c
		pcntl_signal(SIGINT, $stop);
		// Pause Process
		pcntl_signal(SIGUSR2, function () {
			$this->working = false;
			$this->closeConnection();
			$this->info("Connection closed");
		});
		// Continue Process
		pcntl_signal(SIGCONT, function () {
			try {
				$this->connect();
				$this->working = true;
				$this->info("Connection reopened");
			} catch (InternalException $e) {
				$this->error("Failed to reopen connection: {$e->getMessage()}");
			}
		});
	}
	
	protected function connect(): void
	{
		$this->channel = $this->connection->channel();
		$this->channel->queue_declare($this->queueName, false, false, false, false);
		$this->channel->basic_qos(0, 1, false);
		$this->channel->basic_consume(
			$this->queueName,
			'',
			false,
			false,
			false,
			false,
			fn(AMQPMessage $request) => $this->handleMessage($request)
		);
	}
	
	protected function handleMessage(AMQPMessage $request): void
	{
		if (!file_exists(base_path('routes/rabbit.php'))) {
			$this->respond(RabbitApiResponse::createRabbitError(RabbitException::routeFileNotFound()), $request);
			DB::reconnect();
			
			return;
		}
		
		$headers = $request->get_properties()['application_headers']?->getNativeData() ?? [];
		$locale  = $headers['w-locale'] ?? 'ru';
		$auth    = $headers['w-auth'] ?? null;
		
		app()->setLocale($this->availableLocales->contains($locale) ? $locale : 'ru');
		
		$requestBody = json_decode($request->getBody() ?: '{}', true, 512, JSON_THROW_ON_ERROR);
		$method      = $requestBody['method'] ?? null;
		$params      = $requestBody['params'] ?? [];
		$routes      = require base_path('routes/rabbit.php');
		
		if (!$method) {
			$this->respond(RabbitApiResponse::createRabbitError(RabbitException::requestMethodNotFound()), $request);
			DB::reconnect();
			
			return;
		}
		
		$dispatcher = new RabbitMQChannelDispatcher($auth, $method, $routes, $params);
		$this->respond($dispatcher->call(), $request);
		DB::reconnect();
	}
	
	protected function respond(RabbitApiResponse $response, AMQPMessage $request): void
	{
		if (!$request->has('correlation_id')) {
			$request->nack();
			
			return;
		}
		
		$reply = new AMQPMessage($response->toJson(), ['correlation_id' => $request->get('correlation_id')]);
		$request->getChannel()?->basic_publish($reply, '', $request->get('reply_to'));
		$request->ack();
	}
	
	protected function closeConnection(): void
	{
		try {
			$this->channel->close();
			$this->connection->close();
		} catch (Throwable $exception) {
			Log::error($exception);
		}
	}
	
}
