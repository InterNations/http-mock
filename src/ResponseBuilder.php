<?php

namespace InterNations\Component\HttpMock;

use Closure;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Opis\Closure\SerializableClosure;
use Psr\Http\Message\ResponseInterface;

class ResponseBuilder
{
    private MockBuilder $mockBuilder;

    private ResponseInterface $response;

    /** @var callable */
    private $responseCallback;

    public function __construct(MockBuilder $mockBuilder)
    {
        $this->mockBuilder = $mockBuilder;
        $this->response = new Response();
    }

    public function statusCode(int $statusCode) : self
    {
        $this->response = $this->response->withStatus($statusCode);

        return $this;
    }

    public function body(string $body) : self
    {
        $this->response = $this->response->withBody(Utils::streamFor($body));

        return $this;
    }

    public function callback(Closure $callback) : self
    {
        $this->responseCallback = new SerializableClosure($callback);

        return $this;
    }

    public function header(string $header, string $value) : self
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

    public function getResponseCallback() : ?callable
    {
        return $this->responseCallback;
    }
}
