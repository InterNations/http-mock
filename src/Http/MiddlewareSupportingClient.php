<?php
namespace InterNations\Component\HttpMock\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MiddlewareSupportingClient implements ClientInterface
{
    private ClientInterface $client;

    private array $middlewareStack;

    public function __construct(ClientInterface $client, ClientMiddleware ...$middlewareStack)
    {
        $this->client = $client;
        $this->middlewareStack = array_merge([new EmptyMiddleware()], $middlewareStack);
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $middlewareStack = $this->middlewareStack;

        $next = static function(RequestInterface $request, ClientInterface $client) use (&$middlewareStack, &$next) {
            $middleware = array_pop($middlewareStack);
            return $middleware->process($request, $client, $next);
        };

        return $next($request, $this->client);
    }
}
