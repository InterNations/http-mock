<?php
namespace InterNations\Component\HttpMock\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Request as BaseRequest;

class Request extends BaseRequest
{
    public function setRequestUri($requestUri): void
    {
        $this->requestUri = $requestUri;
    }

    public function setContent($content): void
    {
        $this->content = $content;
    }
}
