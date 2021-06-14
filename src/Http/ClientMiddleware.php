<?php
namespace InterNations\Component\HttpMock\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface ClientMiddleware
{
    public function process(RequestInterface $request, ClientInterface $client, callable $next): ResponseInterface;
}
