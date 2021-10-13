<?php
namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use GuzzleHttp\Psr7\FnStream;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Utils;
use InterNations\Component\HttpMock\PHPUnit\HttpMock;
use InterNations\Component\HttpMock\Tests\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use function http_build_query;

/** @large */
class HttpMockPHPUnitIntegrationTest extends TestCase
{
    use HttpMock;

    public static function setUpBeforeClass(): void
    {
        static::setUpHttpMockBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        static::tearDownHttpMockAfterClass();
    }

    public function setUp(): void
    {
        $this->setUpHttpMock();
    }

    public function tearDown(): void
    {
        $this->tearDownHttpMock();
    }

    /** @return array<array{0:string}> */
    public static function getPaths(): array
    {
        return [
            ['/foo'],
            ['/bar'],
        ];
    }

    /** @dataProvider getPaths */
    public function testSimpleRequest(string $path): void
    {
        $this->http->mock
            ->when()
                ->pathIs($path)
            ->then()
                ->body($path . ' body')
            ->end();
        $this->http->setUp();

        self::assertSame(
            $path . ' body',
            (string) $this->http->client->sendRequest(
                $this->getRequestFactory()->createRequest('GET', $path)
            )->getBody()
        );

        $request = $this->http->requests->latest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $request = $this->http->requests->last();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $request = $this->http->requests->first();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $request = $this->http->requests->at(0);
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $request = $this->http->requests->pop();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        self::assertSame(
            $path . ' body',
            (string) $this->http->client->sendRequest(
                $this->getRequestFactory()->createRequest('GET', $path)
            )->getBody()
        );

        $request = $this->http->requests->shift();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Expected status code 200 from "/_request/last", got 404');
        $this->http->requests->pop();
    }

    public function testErrorLogOutput(): void
    {
        $this->http->mock
            ->when()
                ->callback(static function (): void {error_log('error output');})
            ->then()
            ->end();
        $this->http->setUp();

        $this->http->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/foo'));

        // Should fail during tear down as we have an error_log() on the server side
        try {
            $this->tearDown();
            self::fail('Exception expected');
        } catch (\Exception $e) {
            self::assertNotFalse(strpos($e->getMessage(), 'HTTP mock server standard error output should be empty'));
        }
    }

    public function testFailedRequest(): void
    {
        $response = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/foo'));
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('No matching expectation found', (string) $response->getBody());
    }

    public function testStopServer(): void
    {
        $this->http->server->stop();
    }

    /** @depends testStopServer */
    public function testHttpServerIsRestartedIfATestStopsIt(): void
    {
        $response = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/foo'));
        self::assertSame(404, $response->getStatusCode());
    }

    public function testLimitDurationOfAResponse(): void
    {
        $this->http->mock
            ->once()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http->setUp();
        $firstResponse = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/'));
        self::assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/'));
        self::assertSame(404, $secondResponse->getStatusCode());
        self::assertSame('No matching expectation found', (string) $secondResponse->getBody());

        $this->http->mock
            ->exactly(2)
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http->setUp();
        $firstResponse = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/'));
        self::assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/'));
        self::assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/'));
        self::assertSame(404, $thirdResponse->getStatusCode());
        self::assertSame('No matching expectation found', (string) $thirdResponse->getBody());

        $this->http->mock
            ->any()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http->setUp();
        $firstResponse = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/'));
        self::assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/'));
        self::assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/'));
        self::assertSame(200, $thirdResponse->getStatusCode());
    }

    public function testCallbackOnResponse(): void
    {
        $this->http->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->callback(static function(Response $response): void {$response->setContent('CALLBACK');})
            ->end();
        $this->http->setUp();
        self::assertSame(
            'CALLBACK',
            (string) $this->http->client->sendRequest(
                $this->getRequestFactory()->createRequest('POST', '/')
            )->getBody()
        );
    }

    public function testComplexResponse(): void
    {
        $this->http->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->statusCode(201)
                ->header('X-Foo', 'Bar')
                ->body('BODY')
            ->end();
        $this->http->setUp();
        $response = $this->http->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withHeader('x-client-header', 'header-value')
                ->withBody($this->getStreamFactory()->createStream(http_build_query(['post-key' => 'post-value'])))
        );
        self::assertSame('BODY', (string) $response->getBody());
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Bar', $response->getHeaderLine('X-Foo'));
        self::assertSame('post-value', $this->http->requests->latest()->request->get('post-key'));
    }

    public function testPutRequest(): void
    {
        $this->http->mock
            ->when()
                ->methodIs('PUT')
            ->then()
            ->body('BODY')
                ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http->setUp();
        $response = $this->http->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('PUT', '/')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withHeader('x-client-header', 'header-value')
                ->withBody($this->getStreamFactory()->createStream(http_build_query(['put-key' => 'put-value'])))
        );
        self::assertSame('BODY', (string) $response->getBody());
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Bar', $response->getHeaderLine('X-Foo'));
        self::assertSame('put-value', $this->http->requests->latest()->request->get('put-key'));
    }

    public function testPostRequest(): void
    {
        $this->http->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('BODY')
            ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http->setUp();
        $response = $this->http->client->sendRequest(
            $this->getRequestFactory()
                ->createRequest('POST', '/')
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withHeader('x-client-header', 'header-value')
                ->withBody($this->getStreamFactory()->createStream(http_build_query(['post-key' => 'post-value'])))
        );
        self::assertSame('BODY', (string) $response->getBody());
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Bar', $response->getHeaderLine('X-Foo'));
        self::assertSame('post-value', $this->http->requests->latest()->request->get('post-key'));
    }

    public function testPostRequestWithFile(): void
    {
        $this->http->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('BODY')
            ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http->setUp();

        $f1 = FnStream::decorate(Utils::streamFor('file-content'), [
            'getMetadata' => function () {
                return '/foo/bar.txt';
            }
        ]);

        $multipartStream = new MultipartStream([
            [
                'name'     => 'post-key',
                'contents' => 'post-value'
            ],
            [
                'name'     => 'foo',
                'contents' => $f1
            ]
        ]);
        $boundary = $multipartStream->getBoundary();

        $response = $this->http->client->sendRequest(
            $this->getServerRequestFactory()
                ->createServerRequest('POST', '/')
                ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary)
                ->withBody($multipartStream)
        );

        $latestRequest = $this->http->requests->latest();
        self::assertSame('BODY', (string) $response->getBody());
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Bar', $response->getHeaderLine('X-Foo'));
        self::assertSame('post-value', $latestRequest->request->get('post-key'));
        self::assertSame('file-content', $latestRequest->files->get('foo')->getContent());
    }

    public function testPostRequestWithMultipleFiles(): void
    {
        $this->http->mock
            ->when()
            ->methodIs('POST')
            ->then()
            ->body('BODY')
            ->statusCode(201)
            ->header('X-Foo', 'Bar')
            ->end();
        $this->http->setUp();

        $f1 = FnStream::decorate(Utils::streamFor('first-file-content'), [
            'getMetadata' => function () {
                return '/foo/bar.txt';
            }
        ]);

        $f2 = FnStream::decorate(Utils::streamFor('second-file-content'), [
            'getMetadata' => function () {
                return '/foo/baz.txt';
            }
        ]);

        $multipartStream = new MultipartStream([
            [
                'name'     => 'post-key',
                'contents' => 'post-value'
            ],
            [
                'name'     => 'foo',
                'contents' => $f1
            ],
            [
                'name'     => 'bar',
                'contents' => $f2
            ]
        ]);
        $boundary = $multipartStream->getBoundary();

        $response = $this->http->client->sendRequest(
            $this->getServerRequestFactory()
                ->createServerRequest('POST', '/')
                ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary)
                ->withBody($multipartStream)
        );

        $latestRequest = $this->http->requests->latest();
        self::assertSame('BODY', (string) $response->getBody());
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Bar', $response->getHeaderLine('X-Foo'));
        self::assertSame('post-value', $latestRequest->request->get('post-key'));
        self::assertSame('first-file-content', $latestRequest->files->get('foo')->getContent());
        self::assertSame('second-file-content', $latestRequest->files->get('bar')->getContent());
    }

    public function testCountRequests(): void
    {
        $this->http->mock
            ->when()
                ->pathIs('/resource')
            ->then()
                ->body('resource body')
            ->end();
        $this->http->setUp();

        self::assertCount(0, $this->http->requests);
        self::assertSame(
            'resource body',
            (string) $this->http->client->sendRequest(
                $this->getRequestFactory()->createRequest('GET', '/resource')
            )->getBody()
        );
        self::assertCount(1, $this->http->requests);
    }

    public function testMatchQueryString(): void
    {
        $this->http->mock
            ->when()
                ->callback(
                    static function (Request $request) {
                        return $request->query->has('key1');
                    }
                )
                ->methodIs('GET')
            ->then()
                ->body('query string')
            ->end();
        $this->http->setUp();

        self::assertSame(
            'query string', (string) $this->http->client->sendRequest(
            $this->getRequestFactory()->createRequest('GET', '/?key1='))->getBody()
        );
        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $this->http->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/'))->getStatusCode()
        );
        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $this->http->client->sendRequest($this->getRequestFactory()->createRequest('POST', '/'))->getStatusCode()
        );
    }

    public function testMatchRegex(): void
    {
        $this->http->mock
            ->when()
                ->methodIs($this->http->matches->regex('/(GET|POST)/'))
            ->then()
                ->body('response')
            ->end();
        $this->http->setUp();

        self::assertSame(
            'response',
            (string) $this->http->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/'))->getBody()
        );
        self::assertSame(
            'response',
            (string) $this->http->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/'))->getBody()
        );
    }

    public function testMatchQueryParams(): void
    {
        $this->http->mock
            ->when()
                ->queryParamExists('p1')
                ->queryParamIs('p2', 'v2')
                ->queryParamNotExists('p3')
                ->queryParamsExist(['p4'])
                ->queryParamsAre(['p5' => 'v5', 'p6' => 'v6'])
                ->queryParamsNotExist(['p7'])
            ->then()
                ->body('response')
            ->end();
        $this->http->setUp();

        self::assertSame(
            'response',
            (string) $this->http->client->sendRequest(
                $this->getRequestFactory()->createRequest('GET', '/?p1=&p2=v2&p4=any&p5=v5&p6=v6'))->getBody()
            );
        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $this->http->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/?p1=&p2=v2&p3=foo'))
                ->getStatusCode()
        );
        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $this->http->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/?p1='))->getStatusCode()
        );
        self::assertSame(
            Response::HTTP_NOT_FOUND,
            $this->http->client->sendRequest($this->getRequestFactory()->createRequest('GET', '/?p3=foo'))
                ->getStatusCode()
        );
    }

    public function testFatalError(): void
    {
        if (PHP_VERSION_ID < 70000) {
            self::markTestSkipped('Comment in to test if fatal errors are properly handled');
        }

        $this->expectException('Error');

        $this->expectExceptionMessage('Cannot instantiate abstract class');
        new TestCase();
    }
}
