<?php
namespace InterNations\Component\HttpMock\PHPUnit;

use Guzzle\Http\Client;
use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\MockBuilder;
use InterNations\Component\HttpMock\RequestCollectionFacade;
use InterNations\Component\HttpMock\Server;
use RuntimeException;

/**
 * @property-read Server $server The HTTP mock server that is currently running
 * @property-read MatcherFactory $matches An instance of the matcher factory
 * @property-read MockBuilder $mock An instance of the mock builder
 * @property-read RequestCollectionFacade $requests Convenient access to recorded requests
 * @property-read Client $client A pre configured HTTP for client for the currently running server
 */
class HttpMockFacade
{
    const PROPERTIES = ['server', 'matches', 'mock', 'requests', 'client'];

    /** @var array  */
    private $services = [];

    private $basePath;

    public function __construct($port, $host, $basePath)
    {
        $server = new Server($port, $host);
        $server->start();
        $this->services['server'] = $server;
        $this->basePath = $basePath;
    }

    public function setUp()
    {
        $this->server->setUp($this->mock->flushExpectations());
    }

    public function __get($property)
    {
        if (isset($this->services[$property])) {
            return $this->services[$property];
        }

        $this->initializeService($property);

        return $this->services[$property];
    }

    private function initializeService($property)
    {
        switch ($property) {
            case 'matches':
                $this->services['matches'] = new MatcherFactory();
                break;

            case 'mock':
                $this->services['mock'] = new MockBuilder($this->matches, new ExtractorFactory($this->basePath));
                break;

            case 'client':
                $this->services['client'] = $this->server->getClient();
                break;

            case 'requests':
                $this->services['requests'] = new RequestCollectionFacade($this->client);
                break;

            default:
                throw new RuntimeException(sprintf('Invalid property "%s" read', $property));
                break;
        }
    }

    public function __clone()
    {
        $this->server->clean();
    }

    public function each(callable $callback)
    {
        $callback($this);
    }
}
