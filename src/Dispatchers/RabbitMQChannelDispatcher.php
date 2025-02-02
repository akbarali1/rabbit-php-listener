<?php
declare(strict_types=1);

namespace Akbarali\RabbitListener\Dispatchers;

use Akbarali\ActionData\ActionDataBase;
use Akbarali\ActionData\ActionDataException;
use Akbarali\RabbitListener\Contracts\RabbitConsumerContract;
use Akbarali\RabbitListener\Exceptions\InternalException;
use Akbarali\RabbitListener\Exceptions\RabbitException;
use Akbarali\RabbitListener\Presenters\RabbitApiResponse;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * @var array $routes
 * @link /routes/rabbit.php route file
 */
readonly class RabbitMQChannelDispatcher
{
	public function __construct(
		protected mixed $auth,
		protected string $method,
		protected array $routes,
		protected array $params = []
	) {}
	
	protected function getAuthParam(): array
	{
		return isset($this->auth) ? ['auth' => $this->auth] : [];
	}
	
	/**
	 * @return RabbitApiResponse
	 */
	public function call(): RabbitApiResponse
	{
		try {
			$route        = $this->routes[$this->method] ?? throw RabbitException::routeNotFound();
			$service      = $route['service'][0] ?? throw RabbitException::serviceNotFound();
			$method       = $route['service'][1] ?? throw RabbitException::serviceMethodNotFound();
			$authRequired = $route['auth'] ?? false;
			
			$this->validateServiceInterface($service);
			$this->checkAuthentication($authRequired);
			
			$instance = app()->make($service, $this->getAuthParam());
			$params   = $this->resolveParameters($instance, $method, $route['parameterType'] ?? null);
			
			return new RabbitApiResponse(is_object($params) ? $instance->{$method}($params) : $instance->{$method}(...$params));
		} catch (Throwable $e) {
			return $this->handleException($e);
		}
	}
	
	/**
	 * @throws ReflectionException
	 * @throws RabbitException
	 */
	private function validateServiceInterface(string $service): void
	{
		if (!(new ReflectionClass($service))->implementsInterface(RabbitConsumerContract::class)) {
			throw RabbitException::serviceInterfaceNotImplemented();
		}
	}
	
	/**
	 * @throws RabbitException
	 */
	private function checkAuthentication(bool $authRequired): void
	{
		if ($authRequired && is_null($this->auth)) {
			throw RabbitException::unauthenticatedRequest();
		}
	}
	
	/**
	 * @throws ActionDataException
	 * @throws ReflectionException
	 * @throws RabbitException
	 * @throws ValidationException
	 */
	private function resolveParameters(object $instance, string $method, ?string $parameterType): array|object
	{
		if ($parameterType && is_subclass_of($parameterType, ActionDataBase::class)) {
			return [$parameterType::fromArray($this->params)];
		}
		
		$reflection = new ReflectionMethod($instance, $method);
		/** @var \ReflectionParameter[] $parametrs */
		$parameters = $reflection->getParameters();
		if (count($parameters) === 1) {
			$singleParam = $parameters[0];
			$type        = $singleParam->getType();
			if ($type) {
				$paramClassName = $type->getName();
				if (class_exists($paramClassName) && method_exists($paramClassName, 'fromArray')) {
					return $paramClassName::fromArray($this->params);
				}
				
				if (empty($this->params) && $type->allowsNull()) {
					return [];
				}
				if ($singleParam->isDefaultValueAvailable()) {
					return [$singleParam->getName() => $singleParam->getDefaultValue()];
				}
				if (isset($this->params[$singleParam->getName()])) {
					return [$singleParam->getName() => $this->params[$singleParam->getName()]];
				}
				
				throw RabbitException::invalidParams(["\${$singleParam->getName()} is required {$type}"]);
			}
		}
		$params          = [];
		$paramsNotFilled = [];
		foreach ($parameters as $param) {
			$name          = $param->getName();
			$params[$name] = $this->params[$name] ?? ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : null);
			if (is_null($params[$name]) && !$param->isOptional()) {
				$paramsNotFilled[] = "$name is required";
			}
		}
		
		if ($paramsNotFilled) {
			throw RabbitException::invalidParams($paramsNotFilled);
		}
		
		return $params;
	}
	
	private function handleException(Throwable $e): RabbitApiResponse
	{
		$logChannel = match (true) {
			$e instanceof BindingResolutionException,
				$e instanceof ReflectionException => 'daily',
			default                               => 'default',
		};
		
		Log::channel($logChannel)->error($e);
		
		return match (true) {
			$e instanceof ActionDataException => RabbitApiResponse::createRabbitError(RabbitException::actionDataError($e)),
			$e instanceof ValidationException => RabbitApiResponse::createRabbitError(RabbitException::validationError($e)),
			$e instanceof RabbitException     => RabbitApiResponse::createRabbitError($e),
			$e instanceof InternalException   => RabbitApiResponse::createInternalError($e),
			default                           => RabbitApiResponse::createRabbitError(RabbitException::unknownError($e)),
		};
	}
}

