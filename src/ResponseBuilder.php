<?php

namespace InterNations\Component\HttpMock;

use Closure;
use GuzzleHttp\Psr7\Response;
use function GuzzleHttp\Psr7\stream_for;
use Psr\Http\Message\ResponseInterface;
use SuperClosure\SerializableClosure;
use SuperClosure\SerializerInterface;

class ResponseBuilder
{
    /** @var MockBuilder */
    private $mockBuilder;

    /** @var Response */
    private $response;

    /** @var SerializerInterface|null */
    private $responseCallback;

    public function __construct(MockBuilder $mockBuilder)
    {
        $this->mockBuilder = $mockBuilder;
        $this->response = new Response();
    }

    public function statusCode($statusCode) : self
    {
        $this->response = $this->response->withStatus($statusCode);

        return $this;
    }

    public function body($body)
    {
        $this->response = $this->response->withBody(stream_for($body));

        return $this;
    }

    public function callback(Closure $callback) : self
    {
        $this->responseCallback = new SerializableClosure($callback);

        return $this;
    }

    public function header($header, $value) : self
    {
        $this->response = $this->response->withHeader($header, $value);

        return $this;
    }

    public function end() : MockBuilder
    {
        return $this->mockBuilder;
    }

    public function getResponse() : ResponseInterface
    {
        return $this->response;
    }

    public function getResponseCallback()
    {
        return $this->responseCallback;
    }
}
