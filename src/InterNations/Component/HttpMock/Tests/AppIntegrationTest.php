<?php
namespace InterNations\Component\HttpMock\Tests;


use InterNations\Component\HttpMock\Server;
use PHPUnit_Framework_TestCase as TestCase;
use Guzzle\Http\Client;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Message\Response as GuzzleResponse;
use Guzzle\Http\Message\EntityEnclosingRequest;
use SuperClosure\SuperClosure;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Process\Process;

class AppIntegrationTest extends TestCase
{
    /**
     * @var Server
     */
    private static $server1;

    /**
     * @var Client
     */
    private $client;

    public static function setUpBeforeClass()
    {
        static::$server1 = new Server(HTTP_MOCK_PORT, HTTP_MOCK_HOST);
        static::$server1->start();
    }

    public static function tearDownAfterClass()
    {
        error_log(static::$server1->getOutput());
        error_log(static::$server1->getErrorOutput());
        static::$server1->stop();
    }

    public function setUp()
    {
        static::$server1->clean();
        $this->client = static::$server1->getClient();
    }

    public function testSimpleUseCase()
    {
        $response = $this->client->post(
            '/_expectation',
            null,
            $this->createExpectationParams(
                [
                    static function ($request) {
                        return $request instanceof \Symfony\Component\HttpFoundation\Request;
                    }
                ],
                new Response('fake body', 200)
            )
        )->send();
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame(201, $response->getStatusCode());

        $response = $this->client->post('/foobar', ['X-Special' => 1], ['post' => 'data'])->send();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('fake body', (string) $response->getBody());

        $response = $this->client->get('/_request/latest')->send();

        /** @var EntityEnclosingRequest $request */
        $request = $this->parseRequestFromResponse($response);
        $this->assertSame('1', (string) $request->getHeader('X-Special'));
        $this->assertSame('post=data', (string) $request->getBody());
    }

    public function testRecording()
    {
        $this->client->delete('/_all')->send();

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

    public function testErrorWhenNoMatchersPassed()
    {
        $this->client->delete('/_all')->send();

        $response = $this->client->post('/_expectation', null, ['matcher' => null])->send();
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "matcher" must be a serialized list of closures', (string) $response->getBody());

        $response = $this->client->post('/_expectation', null, ['matcher' => ['foo']])->send();
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "matcher" must be a serialized list of closures', (string) $response->getBody());

        $response = $this->client->post('/_expectation', null, [])->send();
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "response" not found in POST data', (string) $response->getBody());

        $response = $this->client->post('/_expectation', null, ['response' => null])->send();
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "response" must be a serialized Symfony response', (string) $response->getBody());
    }

    private function parseRequestFromResponse(GuzzleResponse $response)
    {
        return RequestFactory::getInstance()->fromMessage($response->getBody());
    }

    private function createExpectationParams(array $closures, Response $response)
    {
        foreach ($closures as $index => $closure) {
            $closures[$index] = new SuperClosure($closure);
        }

        return [
            'matcher' => serialize($closures),
            'response' => serialize($response),
        ];
    }
}
