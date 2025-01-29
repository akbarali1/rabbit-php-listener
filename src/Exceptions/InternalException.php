<?php
declare(strict_types=1);

namespace Akbarali\RabbitListener\Exceptions;

use Akbarali\RabbitListener\Enums\ExceptionCode;
use App\Enums\UserExceptionCode;
use Exception;

class InternalException extends Exception
{
	protected string                          $description;
	protected array                           $langParams = [];
	protected ExceptionCode|UserExceptionCode $internalCode;
	
	public static function new(
		ExceptionCode|UserExceptionCode $code,
		?string $message = null,
		?string $description = null,
		?int $statusCode = null,
	): static {
		$exception = new static(
			$message ?? $code->getMessage(),
			$statusCode ?? $code->getStatusCode(),
		);
		
		$exception->internalCode = $code;
		$exception->description  = $description ?? $code->getDescription();
		
		return $exception;
	}
	
	public static function newLangParams(
		ExceptionCode|UserExceptionCode $code,
		array $langParams,
		?string $message = null,
		?string $description = null,
		?int $statusCode = null,
	): static {
		$exception               = new static(
			$message ?? $code->getMessage(),
			$statusCode ?? $code->getStatusCode(),
		);
		$exception->internalCode = $code;
		$exception->langParams   = $langParams;
		$exception->description  = $description ?? $code->getDescriptionParams($langParams);
		
		return $exception;
	}
	
	public static function from(
		int $code,
		?string $message = null,
		?string $description = null,
		?int $statusCode = null,
	): static {
		$exception = new static(
			$message ?? ExceptionCode::findExceptionCode($code)->getMessage(),
			$statusCode ?? ExceptionCode::findExceptionCode($code)->getStatusCode(),
		);
		
		$exception->internalCode = ExceptionCode::findExceptionCode($code);
		$exception->description  = $description ?? ExceptionCode::findExceptionCode($code)->getDescription();
		
		return $exception;
	}
	
	public function getInternalCode(): ExceptionCode|UserExceptionCode
	{
		return $this->internalCode;
	}
	
	public function checkInternalCode(ExceptionCode|UserExceptionCode $code): bool
	{
		return $code === $this->getInternalCode();
	}
	
	public function getDescription(): string
	{
		return $this->description;
	}
}