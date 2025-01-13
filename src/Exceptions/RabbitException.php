<?php
declare(strict_types=1);

namespace Akbarali\RabbitListener\Exceptions;

use Akbarali\ActionData\ActionDataException;
use Akbarali\RabbitListener\Enums\ExceptionCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class RabbitException extends InternalException
{
	
	public static function serviceNotFound(): static
	{
		return static::new(
			code: ExceptionCode::RabbitServiceNotFound,
		);
	}
	
	public static function configNameNotSet(): static
	{
		return static::new(
			code: ExceptionCode::RabbitConfigNameNotSet,
		);
	}
	
	public static function routeNotFound(): static
	{
		return static::new(
			code: ExceptionCode::RabbitRouteNotFound,
		);
	}
	
	public static function invalidParams(array $string): static
	{
		return static::new(
			code       : ExceptionCode::RabbitInvalidParams,
			description: json_encode($string),
		);
	}
	
	public static function reflectorError(string $getMessage): static
	{
		return static::new(
			code   : ExceptionCode::RabbitReflectorError,
			message: $getMessage,
		);
	}
	
	public static function actionDataError(ActionDataException $e): static
	{
		return static::new(
			code       : ExceptionCode::RabbitActionDataError,
			message    : $e->getMessage(),
			description: $e->getTraceAsString(),
		);
	}
	
	public static function unknownError(Throwable $exception): static
	{
		Log::error("Raw exception");
		Log::error($exception->getMessage());
		Log::error($exception->getTraceAsString());
		
		return static::new(
			code: ExceptionCode::RabbitUnknownError,
		);
	}
	
	public static function serviceMethodNotFound(): static
	{
		return static::new(
			code: ExceptionCode::RabbitServiceMethodNotFound,
		);
	}
	
	public static function requestMethodNotFound(): static
	{
		return static::new(
			code: ExceptionCode::RabbitRequestMethodNotFound,
		);
	}
	
	public static function validationError(ValidationException $e): static
	{
		Log::error("Validation error", ['errors' => $e->errors()]);
		
		return static::new(
			code       : ExceptionCode::ValidationException,
			//message    : $e->getMessage(),
			description: json_encode($e->errors()),
		);
	}
	
	public static function serviceInterfaceNotImplemented(): static
	{
		return static::new(
			code: ExceptionCode::RabbitServiceInterfaceNotImplemented,
		);
	}
	
	public static function unauthenticatedRequest(): static
	{
		return static::new(
			code: ExceptionCode::RabbitUnauthenticatedRequest,
		);
	}
	
	public static function routeFileNotFound(): static
	{
		return static::new(
			code: ExceptionCode::RabbitRouteFileNotFound,
		);
	}
	
	public static function notSupportedFunction(): static
	{
		return static::new(
			code: ExceptionCode::RabbitFunctionNotSupported,
		);
	}
	
	public static function responseError(string $message): static
	{
		return static::new(
			code   : ExceptionCode::RabbitResponseError,
			message: $message,
		);
	}
	
}