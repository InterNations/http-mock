<?php
namespace InterNations\Component\HttpMock\Tests;

use Http\Discovery\Psr17FactoryDiscovery;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

abstract class TestCase extends PhpUnitTestCase
{
    public function getRequestFactory(): RequestFactoryInterface
    {
        return Psr17FactoryDiscovery::findRequestFactory();
    }

    public function getResponseFactory(): ResponseFactoryInterface
    {
        return Psr17FactoryDiscovery::findResponseFactory();
    }

    public function getStreamFactory(): StreamFactoryInterface
    {
        return Psr17FactoryDiscovery::findStreamFactory();
    }

    public function getServerRequestFactory(): ServerRequestFactoryInterface
    {
        return Psr17FactoryDiscovery::findServerRequestFactory();
    }
}
