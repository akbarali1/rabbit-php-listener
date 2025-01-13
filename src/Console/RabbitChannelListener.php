<?php

namespace Akbarali\RabbitListener\Console;

use Akbarali\RabbitListener\Dispatchers\RabbitMQChannelDispatcher;
use Akbarali\RabbitListener\Exceptions\InternalException;
use Akbarali\RabbitListener\Exceptions\RabbitException;
use Akbarali\RabbitListener\Managers\CacheManager;
use Akbarali\RabbitListener\Presenters\RabbitApiResponse;
use Closure;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

class RabbitChannelListener extends Command
{
	#region Properties
	protected                      $signature   = 'rabbit:channel:queue {queue}';
	protected                      $description = 'Start to listening rabbitmq';
	protected AMQPStreamConnection $connection;
	protected string               $connectionVhost;
	protected string               $connectionUser;
	protected string               $connectionPort;
	protected string               $connectionHost;
	protected string               $queueName;
	protected string               $connectionPassword;
	protected Collection           $availableLocales;
	protected string               $connectionName;
	protected Closure              $callback;
	protected AMQPChannel          $channel;
	protected bool                 $working     = true;
	
	#endregion
	protected array $routes;
	
	/**
	 * @throws Exception
	 */
	public function __construct(
		protected CacheManager $cacheManager
	) {
		$config                   = config('rabbit', []);
		$this->connectionHost     = $config['connection']['host'];
		$this->connectionPort     = $config['connection']['port'];
		$this->connectionUser     = $config['connection']['user'];
		$this->connectionPassword = $config['connection']['password'];
		$this->connectionVhost    = $config['connection']['vhost'];
		if (!app()->isLocal()) {
			$this->connection = new AMQPStreamConnection($this->connectionHost, $this->connectionPort, $this->connectionUser, $this->connectionPassword, $this->connectionVhost);
		}
		
		$this->availableLocales = collect($config['available_locales'] ?? []);
		
		$this->initCallback();
		
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
		$this->info(string: "$this->signature - $this->connectionName -- started");
		$this->checkConnection();
		$this->connect();
		
		try {
			while ($this->channel->is_open() && !$shallStopWorking) {
				pcntl_signal_dispatch();
				if ($this->working) {
					$this->channel->wait();
				}
			}
			$this->info("$this->signature - $this->connectionName -- end");
		} catch (Throwable $exception) {
			Log::error($exception);
			$this->error($exception->getMessage());
			$this->info("$this->signature - $this->connectionName -- error: {$exception->getMessage()}");
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
		// сигнал об остановке от supervisord
		pcntl_signal(SIGTERM, function () use (&$shallStopWorking) {
			$this->info("Received SIGTERM\n");
			$shallStopWorking = true;
		});
		
		// Close Terminal
		pcntl_signal(SIGHUP, function () use (&$shallStopWorking) {
			$this->info("Received SIGTERM\n");
			$shallStopWorking = true;
		});
		
		// обработчик для ctrl+c
		pcntl_signal(SIGINT, function () use (&$shallStopWorking) {
			$this->info("Received SIGINT\n");
			$shallStopWorking = true;
		});
		
		// Pause Process
		pcntl_signal(SIGUSR2, function () {
			$this->working = false;
			$this->closeConnection();
			$this->info("Connection close\n");
		});
		
		// Continue Process
		pcntl_signal(SIGCONT, function () {
			try {
				$this->connect();
				$this->working = true;
				$this->info("Connection open\n");
			} catch (InternalException $e) {
				$this->info("Connection open failed: ".$e->getMessage().PHP_EOL);
			}
		});
	}
	
	protected function initCallback(): void
	{
		$this->callback = function (AMQPMessage $request) {
			
			if (!file_exists(base_path('routes/rabbit.php'))) {
				$this->response(RabbitApiResponse::createRabbitError(RabbitException::routeFileNotFound()), $request);
				DB::reconnect();
				
				return;
			}
			
			$props = $request->get_properties();
			
			/** @var AMQPTable $applicationHeaders */
			$applicationHeaders = $props['application_headers'];
			$headers            = $applicationHeaders->getNativeData();
			$locale             = $headers['w-locale'] ?? "ru";
			$auth               = $headers['w-auth'] ?? null;
			$requestBody        = json_decode($request->getBody() ?? "{}", true, 512, JSON_THROW_ON_ERROR);
			$method             = data_get($requestBody, 'method');
			$params             = data_get($requestBody, 'params', []);
			$routes             = require base_path('routes/rabbit.php');
			
			if (in_array($locale, $this->availableLocales->toArray(), true)) {
				app()->setLocale($locale);
			} else {
				app()->setLocale('ru');
			}
			
			if (empty($method)) {
				$this->response(RabbitApiResponse::createRabbitError(RabbitException::requestMethodNotFound()), $request);
				DB::reconnect();
				
				return;
			}
			
			$dispatcher = new RabbitMQChannelDispatcher($auth, $method, $routes, $params);
			
			$this->response($dispatcher->call(), $request);
			
			DB::reconnect();
		};
	}
	
	
	protected function response(RabbitApiResponse $res, AMQPMessage $request): void
	{
		if (!$request->has('correlation_id')) {
			$request->nack();
			
			return;
		}
		
		$msg = new AMQPMessage($res->toJson(), [
			'correlation_id' => $request->get('correlation_id'),
		]);
		
		$request->getChannel()?->basic_publish($msg, '', $request->get('reply_to'));
		$request->ack();
	}
	
	private function connect(): void
	{
		$this->channel = $this->connection->channel();
		$this->channel->queue_declare($this->queueName, false, false, false, false);
		$this->channel->basic_qos(0, 1, false);
		$this->channel->basic_consume($this->queueName, '', false, false, false, false, $this->callback);
	}
	
	private function closeConnection(): void
	{
		try {
			$this->channel->close();
			$this->connection->close();
		} catch (Throwable $exception) {
			Log::error($exception);
		}
	}
	
}
