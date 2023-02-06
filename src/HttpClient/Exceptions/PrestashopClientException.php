<?php
namespace Up3Up\Prestashop\HttpClient\Exceptions;

use Exception;

class PrestashopClientException extends Exception {
    protected $reasonPhrase;
    protected $method;
    protected $requestUrl;
    protected $requestParams;
    public function __construct($HttpCode, $reasonPhrase, $method, $requestUrl, $requestParams, $errorMessage, \Throwable $previous = null)
    {
        $this->reasonPhrase = $reasonPhrase;
        $this->method = $method;
        $this->requestUrl = $requestUrl;
        $this->requestParams = $requestParams;
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

    public function getRequestParams() {
        return $this->requestParams;
    }
}