<?php
namespace InterNations\Component\HttpMock\Tests;

use Closure;
use Guzzle\Http\Message\RequestInterface;
use InterNations\Component\HttpMock\ServerProcess;
use InterNations\Component\HttpMock\Util;
use Guzzle\Http\Message\RequestFactory;
use Opis\Closure\SerializableClosure;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function http_build_query;

/**
 * @large
 * @group integration
 */
class AppIntegrationTest extends TestCase
{
    private static ServerProcess $server1;

    private ClientInterface $client;

    public static function setUpBeforeClass(): void
    {
        static::$server1 = new ServerProcess(HTTP_MOCK_PORT, HTTP_MOCK_HOST);
        static::$server1->start();
    }

    public static function tearDownAfterClass(): void
    {
        static::assertSame('', (string) static::$server1->getOutput(), (string) static::$server1->getOutput());
        static::assertSame(
            '',
            (string) static::$server1->getErrorOutput(),
            (string) static::$server1->getErrorOutput()
        );
        static::$server1->stop();
    }

    public function setUp(): void
    {
        static::$server1->clean();
        $this->client = static::$server1->getClient();
    }

    public function testSimpleUseCase(): void
    {
        $response = $this->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/_expectation')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(
                    $this->getStreamFactory()->createStream(
                        http_build_query(
                            $this->createExpectationParams(
                                [
                                    static function ($request) {
                                        return $request instanceof Request;
                                    },
                                ],
                                new Response('fake body', 200)
                            )
                        )
                    )
                )
        );
        self::assertSame('', (string) $response->getBody());
        self::assertSame(201, $response->getStatusCode());

        $response = $this->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/foobar')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withHeader('X-Special', 1)
                ->withBody($this->getStreamFactory()->createStream(http_build_query(['post' => 'data'])))
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('fake body', (string) $response->getBody());

        $response = $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/last'));

        $request = $this->parseRequestFromResponse($response);
        self::assertSame('1', $request->headers->get('X-Special'));
        self::assertSame('post=data', $request->getContent());
    }

    public function testRecording(): void
    {
        $this->client->sendRequest($this->getRequestFactory()->createRequest('DELETE', '/_all'));

        self::assertSame(404, $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/0'))->getStatusCode());
        self::assertSame(404, $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/first'))->getStatusCode());
        self::assertSame(404, $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/last'))->getStatusCode());

        $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/req/0'));
        $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/req/1'));
        $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/req/2'));
        $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/req/3'));

        self::assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/last')))->getRequestUri()
        );
        self::assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/0')))->getRequestUri()
        );
        self::assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/1')))->getRequestUri()
        );
        self::assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/2')))->getRequestUri()
        );
        self::assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/3')))->getRequestUri()
        );
        self::assertSame(404, $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/4'))->getStatusCode());

        self::assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->sendRequest($this->getRequestFactory()->createRequest('DELETE', '/_request/last')))->getRequestUri()
        );
        self::assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->sendRequest($this->getRequestFactory()->createRequest('DELETE', '/_request/first')))->getRequestUri()
        );
        self::assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/0')))->getRequestUri()
        );
        self::assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/1')))->getRequestUri()
        );
        self::assertSame(404, $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/_request/2'))->getStatusCode());
    }

    public function testErrorHandling(): void
    {
        $this->client->sendRequest($this->getRequestFactory()->createRequest('DELETE', '/_all'));

        $response = $this->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/_expectation')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody($this->getStreamFactory()->createStream(http_build_query(['matcher' => ''])))
        );
        self::assertSame(417, $response->getStatusCode());
        self::assertSame(
            'POST data key "matcher" must be a serialized list of closures',
            (string) $response->getBody()
        );

        $response = $this->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/_expectation')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(
                    $this->getStreamFactory()->createStream(
                        http_build_query(['matcher' => ['foo']])
                    )
                )
        );
        self::assertSame(417, $response->getStatusCode());
        self::assertSame(
            'POST data key "matcher" must be a serialized list of closures',
            (string) $response->getBody()
        );

        $response = $this->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/_expectation'));
        self::assertSame(417, $response->getStatusCode());
        self::assertSame('POST data key "response" not found in POST data', (string) $response->getBody());

        $response = $this->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/_expectation')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody($this->getStreamFactory()->createStream(http_build_query(['response' => ''])))
        );
        self::assertSame(417, $response->getStatusCode());
        self::assertSame(
            'POST data key "response" must be a serialized Symfony response',
            (string) $response->getBody()
        );

        $response = $this->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/_expectation')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(
                    $this->getStreamFactory()->createStream(
                        http_build_query(['response' => serialize(new Response()), 'limiter' => 'foo'])
                    )
                )
        );
        self::assertSame(417, $response->getStatusCode());
        self::assertSame('POST data key "limiter" must be a serialized closure', (string) $response->getBody());
    }

    public function testNewestExpectationsAreFirstEvaluated(): void
    {
        $this->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/_expectation')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(
                    $this->getStreamFactory()->createStream(
                        http_build_query(
                            $this->createExpectationParams(
                                [
                                    static function ($request) {
                                        return $request instanceof Request;
                                    },
                                ],
                                new Response('first', 200)
                            )
                        )
                    )
                )
        );
        self::assertSame('first', (string) $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/'))->getBody());

        $this->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/_expectation')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(
                    $this->getStreamFactory()->createStream(
                        http_build_query(
                            $this->createExpectationParams(
                                [static function ($request) { return $request instanceof Request; }],
                                new Response('second', 200)
                            )
                        )
                    )
                )
        );
        self::assertSame('second', (string) $this->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/'))->getBody());
    }

    public function testServerLogsAreNotInErrorOutput(): void
    {
        $this->client->sendRequest($this->getRequestFactory()->createRequest('DELETE', '/_all'));

        $expectedServerErrorOutput = '[404]: (null) / - No such file or directory';

        self::$server1->addErrorOutput('PHP 7.4.2 Development Server (http://localhost:8086) started' . PHP_EOL);
        self::$server1->addErrorOutput('Accepted' . PHP_EOL);
        self::$server1->addErrorOutput($expectedServerErrorOutput . PHP_EOL);
        self::$server1->addErrorOutput('Closing' . PHP_EOL);

        $actualServerErrorOutput = self::$server1->getErrorOutput();

        self::assertEquals($expectedServerErrorOutput, $actualServerErrorOutput);

        self::$server1->clearErrorOutput();
    }

    private function parseRequestFromResponse(ResponseInterface $response): Request
    {
        return Util::deserialize($response->getBody());
    }

    /**
     * @param list<Closure> $closures
     * @return array{matcher: string, response: string}
     */
    private function createExpectationParams(array $closures, Response $response): array
    {
        foreach ($closures as $index => $closure) {
            $closures[$index] = new SerializableClosure($closure);
        }

        return [
            'matcher' => serialize($closures),
            'response' => serialize($response),
        ];
    }
}
