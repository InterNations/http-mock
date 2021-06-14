<?php
namespace InterNations\Component\HttpMock\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SplStack;
use function array_reduce;

final class MiddlewareSupportingClient implements ClientInterface
{
    private ClientInterface $client;

    /** @var SplStack|iterable<ClientMiddleware> */
    private SplStack $middlewareStack;

    public function __construct(ClientInterface $client, ClientMiddleware ...$middlewareStack)
    {
        $this->client = $client;
        $this->middlewareStack = array_reduce(
            $middlewareStack,
            static function (SplStack $stack, ClientMiddleware $middleware) {
                $stack->push($middleware);

                return $stack;
            },
            new SplStack()
        );
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return self::createNext(clone $this->middlewareStack)($request, $this->client);
    }

    /** @param SplStack|iterable<ClientMiddleware> $pendingMiddlewareStack */
    private static function createNext(SplStack $pendingMiddlewareStack): callable
    {
        return static fn (RequestInterface $request, ClientInterface $client) =>
            count($pendingMiddlewareStack) > 0
            ? $pendingMiddlewareStack->pop()->process($request, $client, self::createNext($pendingMiddlewareStack))
            : $client->sendRequest($request);
    }
}
