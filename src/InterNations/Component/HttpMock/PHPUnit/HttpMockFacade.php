<?php
namespace InterNations\Component\HttpMock\PHPUnit;

use Guzzle\Http\Client;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\MockBuilder;
use InterNations\Component\HttpMock\Server;

class HttpMockFacade
{
    /**
     * @var Server
     */
    public $server;

    /**
     * @var MatcherFactory
     */
    public $matches;

    /**
     * @var MockBuilder
     */
    public $mock;

    /**
     * @var Client
     */
    public $client;

    public function __construct($port, $host)
    {
        $this->server = new Server($port, $host);
        $this->server->start();
        $this->initBuilder();
    }

    public function setUp()
    {
        $this->server->setUp($this->mock->getExpectations());
    }

    private function initBuilder()
    {
        $this->matches = new MatcherFactory();
        $this->mock = new MockBuilder($this->matches);
        $this->client = $this->server->getClient();
    }

    public function __clone()
    {
        $this->initBuilder();
        $this->server->clean();
    }
}
