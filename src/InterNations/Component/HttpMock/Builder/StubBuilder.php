<?php
namespace InterNations\Component\HttpMock\Builder;

use Closure;
use InterNations\Component\HttpMock\Builder\ResponseBuilder;
use InterNations\Component\HttpMock\Response\CallbackResponse;
use Jeremeamia\SuperClosure\SerializableClosure;

class StubBuilder
{
    /** @var ResponseBuilder */
    private $responseBuilder;

    /** @var CallbackResponse */
    private $response;

    public function __construct(ResponseBuilder $responseBuilder)
    {
        $this->responseBuilder = $responseBuilder;
        $this->response = new CallbackResponse();
    }

    public function statusCode($statusCode)
    {
        $this->response->setStatusCode($statusCode);

        return $this;
    }

    public function body($body)
    {
        $this->response->setContent($body);

        return $this;
    }

    public function callback(Closure $callback)
    {
        $this->response->setCallback(new SerializableClosure($callback));

        return $this;
    }

    public function header($header, $value)
    {
        $this->response->headers->set($header, $value);

        return $this;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function end()
    {
        return $this->responseBuilder;
    }
}
