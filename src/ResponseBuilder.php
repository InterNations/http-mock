<?php
namespace InterNations\Component\HttpMock;

use InterNations\Component\HttpMock\Response\CallbackResponse;
use SuperClosure\SerializableClosure;
use Closure;
use Symfony\Component\HttpFoundation\Response;

final class ResponseBuilder
{
    private MockBuilder $mockBuilder;
    private CallbackResponse $response;

    public function __construct(MockBuilder $mockBuilder)
    {
        $this->mockBuilder = $mockBuilder;
        $this->response = new CallbackResponse();
    }

    public function statusCode(int $statusCode): self
    {
        $this->response->setStatusCode($statusCode);

        return $this;
    }

    public function body(string $body): self
    {
        $this->response->setContent($body);

        return $this;
    }

    public function callback(Closure $callback): self
    {
        $this->response->setCallback(new SerializableClosure($callback));

        return $this;
    }

    public function header(string $header, string $value): self
    {
        $this->response->headers->set($header, $value);

        return $this;
    }

    public function end(): MockBuilder
    {
        return $this->mockBuilder;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
