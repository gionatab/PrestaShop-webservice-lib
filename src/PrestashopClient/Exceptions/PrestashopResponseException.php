<?php
namespace PrestashopClient\Exceptions;

use Exception;

class PrestashopResponseException extends Exception {
    public function __construct(string $message = "", int $code = 0, \Throwable|null $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}