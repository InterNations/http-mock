<?php

namespace InterNations\Component\HttpMock;

use GuzzleHttp\Client;
use hmmmath\Fibonacci\FibonacciFactory;
use RuntimeException;
use Symfony\Component\Process\Process;

class Server extends Process
{
    /** @var int */
    private $port;

    /** @var string */
    private $host;

    /** @var Client */
    private $client;

    public function __construct($port, $host)
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

    public function start(callable $callback = null, array $env = [])
    {
        parent::start($callback, $env);

        $this->pollWait();
    }

    public function stop($timeout = 10, $signal = null): int|null
    {
        return parent::stop($timeout, $signal);
    }

    public function getClient()
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    private function createClient()
    {
        return new Client(['base_uri' => $this->getBaseUrl(), 'http_errors' => false]);
    }

    public function getBaseUrl()
    {
        return sprintf('http://%s', $this->getConnectionString());
    }

    public function getConnectionString()
    {
        return sprintf('%s:%d', $this->host, $this->port);
    }

    /**
     * @param Expectation[] $expectations
     *
     * @throws RuntimeException
     */
    public function setUp(array $expectations)
    {
        /** @var Expectation $expectation */
        foreach ($expectations as $expectation) {
            $response = $this->getClient()->post(
                '/_expectation',
                ['json' => [
                    'matcher' => serialize($expectation->getMatcherClosures()),
                    'limiter' => serialize($expectation->getLimiter()),
                    'response' => Util::serializePsrMessage($expectation->getResponse()),
                    'responseCallback' => serialize($expectation->getResponseCallback()),
                ]]
            );

            if ($response->getStatusCode() !== 201) {
                throw new RuntimeException('Could not set up expectations: ' . $response->getBody()->getContents());
            }
        }
    }

    public function clean()
    {
        if (!$this->isRunning()) {
            $this->start();
        }

        $this->getClient()->delete('/_all');
    }

    private function pollWait()
    {
        foreach (FibonacciFactory::sequence(50000, 10000) as $sleepTime) {
            try {
                usleep($sleepTime);
                $this->getClient()->head('/_me');
                break;
            } catch (\Exception $e) {
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

    private static function cleanErrorOutput($output)
    {
        if (!trim($output)) {
            return '';
        }

        $errorLines = [];

        foreach (explode(PHP_EOL, $output) as $line) {
            if (!$line) {
                continue;
            }

            if (!self::stringEndsWithAny($line, ['Accepted', 'Closing', ' started'])) {
                $errorLines[] = $line;
            }
        }

        return $errorLines ? implode(PHP_EOL, $errorLines) : '';
    }

    private static function stringEndsWithAny($haystack, array $needles)
    {
        foreach ($needles as $needle) {
            if (substr($haystack, (-1 * strlen($needle))) === $needle) {
                return true;
            }
        }

        return false;
    }
}
