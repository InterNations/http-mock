<?php

namespace InterNations\Component\HttpMock\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use InterNations\Component\HttpMock\Server;
use InterNations\Component\Testing\AbstractTestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use SuperClosure\SerializableClosure;

/**
 * @large
 * @group integration
 */
class AppIntegrationTest extends AbstractTestCase
{
    /**
     * @var Server
     */
    private static $server1;

    /**
     * @var Client
     */
    private $client;

    public static function setUpBeforeClass() : void
    {
        static::$server1 = new Server(HTTP_MOCK_PORT, HTTP_MOCK_HOST);
        static::$server1->start();
    }

    public static function tearDownAfterClass() : void
    {
        $out = (string) static::$server1->getOutput();
        static::assertSame('', $out, $out);

        $out = (string) static::$server1->getErrorOutput();
        //static::assertSame('', $out, $out);
        echo $out . "\n";

        static::$server1->stop();
    }

    public function setUp() : void
    {
        static::$server1->clean();
        $this->client = static::$server1->getClient();
    }

    public function testSimpleUseCase() : void
    {
        $params = $this->createExpectationParams(
            [
                static function ($request) {
                    return $request instanceof RequestInterface;
                },
        ],
            new Response(200, ['Host' => 'localhost'], 'fake body')
        );

        $response = $this->client->post('/_expectation', ['json' => $params]);
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame(201, $response->getStatusCode());

        $response = $this->client->post('/foobar', [
            'headers' => ['X-Special' => 1],
            'form_params' => ['post' => 'data'],
        ]);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('fake body', (string) $response->getBody());

        $response = $this->client->get('/_request/latest');

        $request = $this->parseRequestFromResponse($response);
        $this->assertSame('1', (string) $request->getHeaderLine('X-Special'));
        $this->assertSame('post=data', (string) $request->getBody());

        // should be the same as latest
        $response = $this->client->get('/_request/last');
        $request = $this->parseRequestFromResponse($response);
        $this->assertSame('1', (string) $request->getHeaderLine('X-Special'));
    }

    public function testRecording()
    {
        $this->client->delete('/_all');

        $this->assertSame(404, $this->client->get('/_request/latest')->getStatusCode());
        $this->assertSame(404, $this->client->get('/_request/0')->getStatusCode());
        $this->assertSame(404, $this->client->get('/_request/first')->getStatusCode());
        $this->assertSame(404, $this->client->get('/_request/last')->getStatusCode());

        $this->client->get('/req/0');
        $this->client->get('/req/1');
        $this->client->get('/req/2');
        $this->client->get('/req/3');

        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/last'))->getUri()->getPath()
        );
        $this->assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->get('/_request/0'))->getUri()->getPath()
        );
        $this->assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->get('/_request/1'))->getUri()->getPath()
        );
        $this->assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->get('/_request/2'))->getUri()->getPath()
        );
        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/3'))->getUri()->getPath()
        );
        $this->assertSame(404, $this->client->get('/_request/4')->getStatusCode());

        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->delete('/_request/last'))->getUri()->getPath()
        );
        $this->assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->delete('/_request/first'))->getUri()->getPath()
        );
        $this->assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->get('/_request/0'))->getUri()->getPath()
        );
        $this->assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->get('/_request/1'))->getUri()->getPath()
        );
        $this->assertSame(404, $this->client->get('/_request/2')->getStatusCode());
    }

    public function testErrorHandling()
    {
        $this->client->delete('/_all');

        $tester = function ($matcher, $response = null, $limiter = null) {
            $payload = [];
            if ($response === null) {
                $payload['response'] = \GuzzleHttp\Psr7\str(new Response(200, [], 'foo'));
            } elseif ($response !== false) {
                $payload['response'] = $response;
            }
            if ($matcher === null) {
                $matcher['matcher'] = serialize([new SerializableClosure(function () { return true; })]);
            } elseif ($matcher !== false) {
                $payload['matcher'] = $matcher;
            }

            if ($limiter !== false && $limiter !== null) {
                $payload['limiter'] = $limiter;
            }

            return $this->client->post('/_expectation', ['json' => $payload]);
        };

        $response = $tester('hi');
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "matcher" must be a serialized list of closures', (string) $response->getBody());

        $response = $tester(['foo']);
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "matcher" must be a serialized list of closures', (string) $response->getBody());

        $response = $tester(null, false);
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "response" not found in POST data', (string) $response->getBody());

        $response = $tester(null, 'foo');
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "response" must be an http response message in text form', (string) $response->getBody());

        $response = $tester(null, null, 'foo');
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "limiter" must be a serialized closure', (string) $response->getBody());
    }

    public function testServerParamsAreRecorded()
    {
        $this->client
            ->get('/foo', [
                'headers' => [
                    'User-Agent' => 'CUSTOM UA',
                ],
                'auth' => ['username', 'password'],
                'version' => '1.0',
            ]);

        $latestRequest = unserialize($this->client->get('/_request/latest')->getBody());

        $this->assertSame(HTTP_MOCK_HOST, $latestRequest['server']['SERVER_NAME']);
        $this->assertSame(HTTP_MOCK_PORT, $latestRequest['server']['SERVER_PORT']);
        $this->assertSame('username', $latestRequest['server']['PHP_AUTH_USER']);
        $this->assertSame('password', $latestRequest['server']['PHP_AUTH_PW']);
        $this->assertSame('HTTP/1.0', $latestRequest['server']['SERVER_PROTOCOL']);
        $this->assertSame('CUSTOM UA', $latestRequest['server']['HTTP_USER_AGENT']);
    }

    public function testNewestExpectationsAreFirstEvaluated()
    {
        $this->client->post(
            '/_expectation',
            ['json' => $this->createExpectationParams(
                [
                    static function ($request) {
                        return $request instanceof RequestInterface;
                    },
                ],
                new Response(200, [], 'first')
            )]
        );
        $this->assertSame('first', $this->client->get('/')->getBody()->getContents());

        $this->client->post(
            '/_expectation',
            ['json' => $this->createExpectationParams(
                [
                    static function ($request) {
                        return $request instanceof RequestInterface;
                    },
                ],
                new Response(200, [], 'second')
            )]
        );
        $this->assertSame('second', $this->client->get('/')->getBody()->getContents());
    }

    public function testServerLogsAreNotInErrorOutput()
    {
        $this->client->delete('/_all');

        $expectedServerErrorOutput = '[404]: (null) / - No such file or directory';

        self::$server1->addErrorOutput('PHP 7.4.2 Development Server (http://localhost:8086) started' . PHP_EOL);
        self::$server1->addErrorOutput('Accepted' . PHP_EOL);
        self::$server1->addErrorOutput($expectedServerErrorOutput . PHP_EOL);
        self::$server1->addErrorOutput('Closing' . PHP_EOL);

        $actualServerErrorOutput = self::$server1->getErrorOutput();

        $this->assertEquals($expectedServerErrorOutput, $actualServerErrorOutput);
    }

    private function parseRequestFromResponse(ResponseInterface $response)
    {
        $body = unserialize($response->getBody());

        return \GuzzleHttp\Psr7\parse_request($body['request']);
    }

    private function createExpectationParams(array $closures, Response $response)
    {
        foreach ($closures as $index => $closure) {
            $closures[$index] = new SerializableClosure($closure);
        }

        return [
            'matcher' => serialize($closures),
            'response' => \GuzzleHttp\Psr7\str($response),
        ];
    }
}
