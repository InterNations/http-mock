<?php
namespace InterNations\Component\HttpMock\Tests\Fixtures;

use InterNations\Component\HttpMock\Request\SerializableRequest;

class Request extends SerializableRequest
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
