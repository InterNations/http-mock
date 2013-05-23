<?php
namespace InterNations\Eos\FakeApi;

use PHPUnit_Framework_TestCase as TestCase;
use Guzzle\Http\Client;
use Guzzle\Common\Event;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Message\EntityEnclosingRequest;
use Symfony\Component\Process\Process;


class AppTest extends TestCase
{
    /**
     * @var Process
     */
    private static $server1;

    /**
     * @var Process
     */
    private static $server2;

    /**
     * @var Client
     */
    private $client;

    /** @var Client */
    private $testServerClient;

    public static function setUpBeforeClass()
    {
        static::$server1 = new Process(
            sprintf('php -n -S 127.0.0.1:%d -t public/ public/index.php', EOS_FAKE_API_SERVER1_PORT)
        );
        static::$server1->start();

        static::$server2 = new Process(
            sprintf('php -n -S 127.0.0.1:%d -t public/ public/index.php', EOS_FAKE_API_SERVER2_PORT)
        );
        static::$server2->start();

        sleep(1);
    }

    public static function tearDownAfterClass()
    {
        error_log(static::$server1->getOutput());
        error_log(static::$server1->getErrorOutput());
        static::$server1->stop();

        error_log(static::$server2->getOutput());
        error_log(static::$server2->getErrorOutput());
        static::$server2->stop();

    }

    public function setUp()
    {
        $this->client = new Client('http://127.0.0.1:' . EOS_FAKE_API_SERVER1_PORT);
        $this->testServerClient = new Client('http://127.0.0.1:' . EOS_FAKE_API_SERVER2_PORT);
        $this->client->getEventDispatcher()->addListener(
            'request.error',
            function(Event $event) {
                $event->stopPropagation();
            }
        );
    }

    public function testSimpleUseCase()
    {
        $response = $this->client->post('/_expectation', null, ['statusCode' => '403', 'body' => 'fake body'])->send();
        $this->assertSame(201, $response->getStatusCode());

        $response = $this->client->post('/foobar', ['X-Special' => 1], ['post' => 'data'])->send();
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('fake body', (string) $response->getBody());

        $response = $this->client->get('/_request/latest')->send();
        $requestString = (string) $response->getBody();

        /** @var EntityEnclosingRequest $request */
        $request = RequestFactory::getInstance()->fromMessage($requestString);
        $this->assertSame('1', (string) $request->getHeader('X-Special'));
        $this->assertSame('post=data', (string) $request->getBody());
    }

    public function testAccessCallback()
    {
        $this->client->post('/_expectation', null, ['callbackUrlPropertyPath' => '[request][url]'])->send();

        $this->client->post(
            '/not/a/callback/url',
            null,
            ['url' => 'http://localhost:' . EOS_FAKE_API_SERVER2_PORT . '/callBack/from/server?param=1234']
        )->send();

        $response = $this->testServerClient->get('/_request/latest')->send();
        $requestString = (string) $response->getBody();

        /** @var EntityEnclosingRequest $request */
        $request = RequestFactory::getInstance()->fromMessage($requestString);

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame(['param' => '1234'], $request->getQuery()->toArray());
        $this->assertSame('/callBack/from/server', $request->getPath());
    }

    public function testAccessCallbackWithInvalidPropertyPath()
    {
        $this->client->post('/_expectation', null, ['callbackUrlPropertyPath' => '[request][url]'])->send();
        $response = $this->client->post(
            '/not/a/callback/url',
            null,
            []
        )->send();

        $this->assertContains('Could not extract property from path &quot;[request][url]&quot;', (string) $response->getBody());
    }

    public function testAccessCallbackWithInvalidPropertyPath_2()
    {
        $this->client->post('/_expectation', null, ['callbackUrlPropertyPath' => 'request.url'])->send();
        $response = $this->client->post(
            '/not/a/callback/url',
            null,
            []
        )->send();

        $this->assertContains('Cannot read property', (string) $response->getBody());
    }

    public function testRecording()
    {
        $this->client->post('/_expectation')->send();

        $this->client->get('/req/0')->send();
        $this->client->get('/req/1')->send();
        $this->client->get('/req/2')->send();
        $this->client->get('/req/3')->send();

        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/latest')->send())->getPath()
        );
        $this->assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->get('/_request/0')->send())->getPath()
        );
        $this->assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->get('/_request/1')->send())->getPath()
        );
        $this->assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->get('/_request/2')->send())->getPath()
        );
        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/3')->send())->getPath()
        );
    }

    public function testSleep()
    {
        $this->client->post('/_expectation', null, ['sleepMs' => 2000])->send();
        $this->client->setConfig(['curl.options' => [CURLOPT_TIMEOUT => 1]]);

        $this->setExpectedException('Guzzle\Http\Exception\CurlException');
        $this->client->get('/timeout')->send();
    }

    private function parseRequestFromResponse(\Guzzle\Http\Message\Response $response)
    {
        return RequestFactory::getInstance()->fromMessage($response->getBody());
    }
}
