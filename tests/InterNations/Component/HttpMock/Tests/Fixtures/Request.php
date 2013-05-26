<?php
namespace InterNations\Component\HttpMock\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Request as BaseRequest;

class Request extends BaseRequest
{
    public function setRequestUri($requestUri)
    {
        $this->requestUri = $requestUri;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }
}
