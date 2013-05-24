<?php
namespace InterNations\Component\HttpMock;

use Guzzle\Http\Client;
use Guzzle\Common\Event;
use Symfony\Component\Process\Process;

class Server extends Process
{
    private $port;

    private $host;

    private $client;

    private $stderr;

    public function __construct($port, $host)
    {
        $this->port = $port;
        $this->host = $host;
        parent::__construct(
            sprintf('exec php -S %s -t public/ public/index.php', $this->getConnectionString()),
            __DIR__ . '/../../../../'
        );
        $this->setTimeout(null);
    }

    public function start($callback = null)
    {
        parent::start($callback);
        sleep(1);
    }

    public function stop($timeout = 10, $signal = null)
    {
        $exitCode = parent::stop($timeout, $signal);

        return $exitCode;
    }

    public function getClient()
    {
        if (!$this->client) {
            $this->client = new Client($this->getBaseUrl());
            $this->client->getEventDispatcher()->addListener(
                'request.error',
                static function (Event $event) {
                    $event->stopPropagation();
                }
            );
        }

        return $this->client;
    }

    public function getBaseUrl()
    {
        return sprintf('http://%s', $this->getConnectionString());
    }

    public function getConnectionString()
    {
        return sprintf('%s:%d', $this->host, $this->port);
    }

    public function addErrorOutput($line)
    {
        $this->stderr .= $line;
    }

    public function getErrorOutput()
    {
        $this->updateErrorOutput();

        return $this->stderr;
    }

    /**
     * @param Expectation[] $expectations
     */
    public function setUp(array $expectations)
    {
        /** @var Expectation $expectation */
        foreach ($expectations as $expectation) {
            $this->getClient()->post(
                '/_expectation',
                null,
                [
                    'matcher'  => serialize($expectation->getMatcherClosures()),
                    'response' => serialize($expectation->getResponse())
                ]
            )->send();
        }
    }

    public function clean()
    {
        $this->getClient()->delete('/_all')->send();

        $this->clearErrorOutput();
    }

    private function clearErrorOutput()
    {
        // Clear error output
        $this->stderr = '';
    }
}
