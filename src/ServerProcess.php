<?php
namespace InterNations\Component\HttpMock;

use hmmmath\Fibonacci\FibonacciFactory;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use InterNations\Component\HttpMock\Http\BaseUriMiddleware;
use InterNations\Component\HttpMock\Http\MiddlewareSupportingClient;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Process\Process;
use RuntimeException;
use function getenv;

class ServerProcess extends Process
{
    private int $port;

    private string $host;

    private ?ClientInterface $client = null;

    public function __construct(int $port, string $host)
    {
        $this->port = $port;
        $this->host = $host;
        $packageRoot = __DIR__ . '/../';
        $command = [
            'php',
            '-dalways_populate_raw_post_data=-1',
            '-derror_log=',
            '-S=' . $this->getConnectionString(),
            '-t=public/',
            $packageRoot . 'public/index.php',
        ];

        parent::__construct($command, $packageRoot, ['HTTP_MOCK_TESTSUITE' => getenv('HTTP_MOCK_TESTSUITE')]);
        $this->setTimeout(null);
    }

    /** @param array<string,string> $env */
    public function start(callable $callback = null, array $env = []): void
    {
        parent::start($callback, $env);

        $this->pollWait();
    }

    /**
     * @param int|float $timeout
     * @param int $signal
     */
    public function stop($timeout = 10, $signal = null): ?int // @codingStandardsIgnoreLine
    {
        return parent::stop($timeout, $signal);
    }

    public function getClient(): ClientInterface
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    private function createClient(): ClientInterface
    {
        return new MiddlewareSupportingClient(
            Psr18ClientDiscovery::find(),
            new BaseUriMiddleware($this->getBaseUrl())
        );
    }

    public function getBaseUrl(): UriInterface
    {
        return $this->getUriFactory()->createUri(sprintf('http://%s', $this->getConnectionString()));
    }

    private function getUriFactory(): UriFactoryInterface
    {
        return Psr17FactoryDiscovery::findUriFactory();
    }

    private function getRequestFactory(): RequestFactoryInterface
    {
        return Psr17FactoryDiscovery::findRequestFactory();
    }

    private function getStreamFactory(): StreamFactoryInterface
    {
        return Psr17FactoryDiscovery::findStreamFactory();
    }

    public function getConnectionString(): string
    {
        return sprintf('%s:%d', $this->host, $this->port);
    }

    /**
     * @param array<Expectation> $expectations
     * @throws RuntimeException
     */
    public function setUp(array $expectations): void
    {
        foreach ($expectations as $expectation) {
            $response = $this->getClient()->sendRequest(
                $this->getRequestFactory()
                    ->createRequest('POST', '/_expectation')
                    ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                    ->withBody(
                        $this->getStreamFactory()->createStream(
                            http_build_query(
                                [
                                    'matcher' => serialize($expectation->getMatcherClosures()),
                                    'limiter' => serialize($expectation->getLimiter()),
                                    'response' => serialize($expectation->getResponse()),
                                ]
                            )
                        )
                    )
            );

            if ($response->getStatusCode() !== 201) {
                throw new RuntimeException('Could not set up expectations');
            }
        }
    }

    public function clean(): void
    {
        if (!$this->isRunning()) {
            $this->start();
        }

        $this->getClient()->sendRequest($this->getRequestFactory()->createRequest('DELETE', '/_all'));
    }

    private function pollWait(): void
    {
        foreach (FibonacciFactory::sequence(50000, 10000) as $sleepTime) {
            try {
                usleep($sleepTime);
                $this->getClient()->sendRequest($this->getRequestFactory()->createRequest('HEAD', '/_me'));
                break;
            } catch (ClientExceptionInterface $e) {
                continue;
            }
        }
    }

    public function getIncrementalErrorOutput(): string
    {
        return self::cleanErrorOutput(parent::getIncrementalErrorOutput());
    }

    public function getErrorOutput(): string
    {
        return self::cleanErrorOutput(parent::getErrorOutput());
    }

    private static function cleanErrorOutput(string $output): string
    {
        if (!trim($output)) {
            return '';
        }

        $errorLines = [];

        foreach (explode(PHP_EOL, $output) as $line) {
            if (!$line) {
                continue;
            }

            if (self::stringContainsAny(
                $line,
                [
                    'Accepted',
                    'Closing',
                    'Development Server',
                    'JIT is incompatible with third party extensions',
                    ' [info] ',
                    ' [debug] ',
                ]
            )) {
                continue;
            }

            $errorLines[] = $line;
        }

        return $errorLines ? implode(PHP_EOL, $errorLines) : '';
    }

    /** @param list<string> $needles */
    private static function stringContainsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
