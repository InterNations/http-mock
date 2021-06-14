<?php
namespace InterNations\Component\HttpMock\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

final class BaseUriMiddleware implements ClientMiddleware
{
    private UriInterface $baseUri;

    public function __construct(UriInterface $baseUri)
    {
        $this->baseUri = $baseUri;
    }

    public function process(RequestInterface $request, ClientInterface $client, callable $next): ResponseInterface
    {
        $originalUri = $request->getUri();

        $uri = $originalUri
            ->withScheme($this->baseUri->getScheme() ?: $originalUri->getScheme())
            ->withHost($this->baseUri->getHost() ?: $originalUri->getHost())
            ->withPort($this->baseUri->getPort() ?: $originalUri->getPort())
            ->withPath(sprintf('%s/%s', rtrim($this->baseUri->getPath(), '/'), ltrim($originalUri->getPath(), '/')));


        return $next($request->withUri($uri), $client);
    }
}
