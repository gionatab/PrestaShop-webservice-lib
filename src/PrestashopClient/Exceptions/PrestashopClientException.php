<?php
namespace Up3Up\PrestashopClient\Exceptions;

use Exception;

class PrestashopClientException extends Exception {
    protected $reasonPhrase;
    protected $method;
    protected $requestUrl;
    public function __construct($HttpCode, $reasonPhrase, $method, $requestUrl, $errorMessage, \Throwable $previous = null)
    {
        $this->reasonPhrase = $reasonPhrase;
        $this->method = $method;
        $this->requestUrl = $requestUrl;
        parent::__construct($errorMessage, $HttpCode, $previous);
    }

    public function getReasonPhrase() {
        return $this->reasonPhrase;
    }

    public function getMethod() {
        return $this->method;
    }

    public function getRequestUrl() {
        return $this->requestUrl;
    }
}