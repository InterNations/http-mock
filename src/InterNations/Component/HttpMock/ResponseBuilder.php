<?php
namespace InterNations\Component\HttpMock;

use InterNations\Component\HttpMock\Response\CallbackResponse;
use Closure;
use Jeremeamia\SuperClosure\SerializableClosure;

class ResponseBuilder
{
    /** @var MockBuilder */
    private $mockBuilder;

    /** @var CallbackResponse */
    private $response;

    public function __construct(MockBuilder $mockBuilder)
    {
        $this->mockBuilder = $mockBuilder;
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

    public function end()
    {
        return $this->mockBuilder;
    }

    public function getResponse()
    {
        return $this->response;
    }
}
