<?php

namespace InterNations\Component\HttpMock\Tests\Fixtures;

use GuzzleHttp\Psr7\Request as BaseRequest;

class Request extends BaseRequest
{
    private string $requestUri;

    private string $content;

    public function __construct(
        $method = 'GET',
        $uri = '/',
        array $headers = [],
        $body = null,
        $version = '1.1'
    ) {
        parent::__construct($method, $uri, $headers, $body, $version);
    }

    public function setRequestUri($requestUri)
    {
        $this->requestUri = $requestUri;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }
}
