<?php

namespace InterNations\Component\HttpMock;

use Closure;
use GuzzleHttp\Psr7\Response;
use SuperClosure\SerializableClosure;

class ResponseBuilder
{
    /** @var MockBuilder */
    private $mockBuilder;

    /** @var Response */
    private $response;

    private $responseCallback;

    public function __construct(MockBuilder $mockBuilder)
    {
        $this->mockBuilder = $mockBuilder;
        $this->response = new Response();
    }

    public function statusCode($statusCode)
    {
        $this->response = $this->response->withStatus($statusCode);

        return $this;
    }

    public function body($body)
    {
        $this->response = $this->response->withBody(\GuzzleHttp\Psr7\stream_for($body));

        return $this;
    }

    public function callback(Closure $callback)
    {
        $this->responseCallback = new SerializableClosure($callback);

        return $this;
    }

    public function header($header, $value)
    {
        $this->response = $this->response->withHeader($header, $value);

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

    public function getResponseCallback()
    {
        return $this->responseCallback;
    }
}
