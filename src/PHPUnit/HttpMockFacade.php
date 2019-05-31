<?php
namespace Pagely\Component\HttpMock\PHPUnit;

use GuzzleHttp\Client;
use Pagely\Component\HttpMock\Matcher\ExtractorFactory;
use Pagely\Component\HttpMock\Matcher\MatcherFactory;
use Pagely\Component\HttpMock\MockBuilder;
use Pagely\Component\HttpMock\RequestCollectionFacade;
use Pagely\Component\HttpMock\Server;
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

    public static function getProperties()
    {
        return ['server', 'matches', 'mock', 'requests', 'client'];
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

        return $this->services[$property] = $this->createService($property);
    }

    private function createService($property)
    {
        switch ($property) {
            case 'matches':
                return new MatcherFactory();
                break;

            case 'mock':
                return new MockBuilder($this->matches, new ExtractorFactory($this->basePath));
                break;

            case 'client':
                return $this->server->getClient();
                break;

            case 'requests':
                return new RequestCollectionFacade($this->client);
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
