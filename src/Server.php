<?php

namespace InterNations\Component\HttpMock;

use GuzzleHttp\Client;
use hmmmath\Fibonacci\FibonacciFactory;
use RuntimeException;
use Symfony\Component\Process\Process;

class Server extends Process
{
    private $port;

    private $host;

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

    public function stop($timeout = 10, $signal = null)
    {
        return parent::stop($timeout, $signal);
    }

    public function getClient()
    {
        return $this->client ?: $this->client = $this->createClient();
    }

    private function createClient()
    {
        $client = new Client(['base_uri' => $this->getBaseUrl(), 'http_errors' => false]);

        return $client;
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
        $success = false;
        foreach (FibonacciFactory::sequence(50000, 10000, 8) as $sleepTime) {
            try {
                usleep($sleepTime);
                $r = $this->getClient()->head('/_me');
                if ($r->getStatusCode() != 418) {
                    continue;
                }
                $success = true;
                break;
            } catch (ServerException $e) {
                continue;
            }
        }

        if (!$success) {
            throw $e;
        }
    }
}
