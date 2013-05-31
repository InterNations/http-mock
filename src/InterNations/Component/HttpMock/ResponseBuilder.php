<?php
namespace InterNations\Component\HttpMock;

use Symfony\Component\HttpFoundation\Response;

class ResponseBuilder
{
    /** @var MockBuilder */
    private $mockBuilder;

    /** @var Response */
    private $response;

    public function __construct(MockBuilder $mockBuilder)
    {
        $this->mockBuilder = $mockBuilder;
        $this->response = new Response();
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
