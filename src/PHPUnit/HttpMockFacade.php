<?php
namespace InterNations\Component\HttpMock\PHPUnit;

use Guzzle\Http\Client;
use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\MockBuilder;
use InterNations\Component\HttpMock\RequestCollectionFacade;
use InterNations\Component\HttpMock\Server;
use RuntimeException;
use function explode;
use function implode;
use function strlen;
use function substr;
use function trim;
use const PHP_EOL;

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

    /**
     * Get error output (if exists)
     *
     * @return string
     */
    public function getErrorOutput()
    {
        return static::cleanErrorOutput($this->server->getIncrementalErrorOutput());
    }

    /**
     * Get error output (if exists)
     *
     * @param string $output
     *
     * @return string
     */
    public static function cleanErrorOutput($output)
    {
        if (!trim($output)) {
            return '';
        }

        $errorLines = [];

        foreach (explode(PHP_EOL, $output) as $line) {
            if(!$line) {
                continue;
            }
            if (!static::endsWith($line, ['Accepted', 'Closing', ' started'])) {
                //throw new AssertionFailedError($message ?: "PHP Web Server seems to have logged an error: $line");
                $errorLines[] = $line;
            }
        }

        return $errorLines ? implode(PHP_EOL, $errorLines) : '';
    }

    /**
     * Returns true if $haystack contains at least one of $needles.
     *
     * @param string          $haystack
     * @param string|string[] $needles
     *
     * @return bool
     */
    protected static function endsWith($haystack, $needles)
    {
        foreach((array)$needles as $needle) {
            if (substr($haystack, -strlen($needle)) == $needle) {
                return true;
            }
        }
        return false;
    }
}
