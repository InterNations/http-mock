<?php
namespace InterNations\Component\HttpMock;

use Guzzle\Http\Client;
use Guzzle\Common\Event;
use hmmmath\Fibonacci\FibonacciFactory;
use Symfony\Component\Process\Process;
use RuntimeException;
use Guzzle\Http\Exception\CurlException;

class Server extends Process
{
    private int $port;

    private string $host;

    private ?Client $client = null;

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

        parent::__construct($command, $packageRoot);
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

    public function getClient(): Client
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    private function createClient(): Client
    {
        $client = new Client($this->getBaseUrl());
        $client->getEventDispatcher()->addListener(
            'request.error',
            static function (Event $event): void {
                $event->stopPropagation();
            }
        );

        return $client;
    }

    public function getBaseUrl(): string
    {
        return sprintf('http://%s', $this->getConnectionString());
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
            $response = $this->getClient()->post(
                '/_expectation',
                null,
                [
                    'matcher' => serialize($expectation->getMatcherClosures()),
                    'limiter' => serialize($expectation->getLimiter()),
                    'response' => serialize($expectation->getResponse()),
                ]
            )->send();

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

        $this->getClient()->delete('/_all')->send();
    }

    private function pollWait(): void
    {
        foreach (FibonacciFactory::sequence(50000, 10000) as $sleepTime) {
            try {
                usleep($sleepTime);
                $this->getClient()->head('/_me')->send();
                break;
            } catch (CurlException $e) {
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

            if (self::stringEndsWithAny($line, ['Accepted', 'Closing', ' started'])) {
                continue;
            }

            $errorLines[] = $line;
        }

        return $errorLines ? implode(PHP_EOL, $errorLines) : '';
    }

    /** @param list<string> $needles */
    private static function stringEndsWithAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (substr($haystack, (-1 * strlen($needle))) === $needle) {
                return true;
            }
        }

        return false;
    }
}
