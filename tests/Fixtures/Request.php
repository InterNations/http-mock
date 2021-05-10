<?php
namespace InterNations\Component\HttpMock\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Request as BaseRequest;

class Request extends BaseRequest
{
    public function setRequestUri(string $requestUri): void
    {
        $this->requestUri = $requestUri;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
