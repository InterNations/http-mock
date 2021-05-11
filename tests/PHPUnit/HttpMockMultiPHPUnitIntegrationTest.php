<?php
namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use InterNations\Component\Testing\AbstractTestCase;
use InterNations\Component\HttpMock\PHPUnit\HttpMock;
use Symfony\Component\HttpFoundation\Response;
use PHPUnit\Framework\TestCase;

/** @large */
class HttpMockMultiPHPUnitIntegrationTest extends AbstractTestCase
{
    use HttpMock;

    public static function setUpBeforeClass(): void
    {
        static::setUpHttpMockBeforeClass(null, null, null, 'firstNamedServer');
        static::setUpHttpMockBeforeClass(static::getHttpMockDefaultPort() + 1, null, null, 'secondNamedServer');
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
        $this->http['firstNamedServer']->mock
            ->when()
                ->pathIs($path)
            ->then()
                ->body($path . ' body')
            ->end();
        $this->http['firstNamedServer']->setUp();

        self::assertSame($path . ' body', (string) $this->http['firstNamedServer']->client->get($path)->send()->getBody());

        $request = $this->http['firstNamedServer']->requests->latest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $request = $this->http['firstNamedServer']->requests->last();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $request = $this->http['firstNamedServer']->requests->first();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $request = $this->http['firstNamedServer']->requests->at(0);
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $request = $this->http['firstNamedServer']->requests->pop();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        self::assertSame($path . ' body', (string) $this->http['firstNamedServer']->client->get($path)->send()->getBody());

        $request = $this->http['firstNamedServer']->requests->shift();
        self::assertSame('GET', $request->getMethod());
        self::assertSame($path, $request->getRequestUri());

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Expected status code 200 from "/_request/last", got 404');
        $this->http['firstNamedServer']->requests->pop();
    }

    public function testErrorLogOutput(): void
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->callback(static function (): void {error_log('error output');})
            ->then()
            ->end();
        $this->http['firstNamedServer']->setUp();

        $this->http['firstNamedServer']->client->get('/foo')->send();

        // Should fail during tear down as we have an error_log() on the server side
        try {
            $this->tearDown();
            self::fail('Exception expected');
        } catch (\Exception $e) {
            self::assertTrue(strpos($e->getMessage(), 'HTTP mock server standard error output should be empty') !== false);
        }
    }

    public function testFailedRequest(): void
    {
        $response = $this->http['firstNamedServer']->client->get('/foo')->send();
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('No matching expectation found', (string) $response->getBody());
    }

    public function testStopServer(): void
    {
        $this->http['firstNamedServer']->server->stop();
    }

    /** @depends testStopServer */
    public function testHttpServerIsRestartedIfATestStopsIt(): void
    {
        $response = $this->http['firstNamedServer']->client->get('/')->send();
        self::assertSame(404, $response->getStatusCode());
    }

    public function testLimitDurationOfAResponse(): void
    {
        $this->http['firstNamedServer']->mock
            ->once()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $firstResponse = $this->http['firstNamedServer']->client->post('/')->send();
        self::assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http['firstNamedServer']->client->post('/')->send();
        self::assertSame(410, $secondResponse->getStatusCode());
        self::assertSame('Expectation not met', $secondResponse->getBody(true));

        $this->http['firstNamedServer']->mock
            ->exactly(2)
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $firstResponse = $this->http['firstNamedServer']->client->post('/')->send();
        self::assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http['firstNamedServer']->client->post('/')->send();
        self::assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http['firstNamedServer']->client->post('/')->send();
        self::assertSame(410, $thirdResponse->getStatusCode());
        self::assertSame('Expectation not met', $thirdResponse->getBody(true));

        $this->http['firstNamedServer']->mock
            ->any()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $firstResponse = $this->http['firstNamedServer']->client->post('/')->send();
        self::assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http['firstNamedServer']->client->post('/')->send();
        self::assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http['firstNamedServer']->client->post('/')->send();
        self::assertSame(200, $thirdResponse->getStatusCode());
    }

    public function testCallbackOnResponse(): void
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->callback(static function(Response $response): void {$response->setContent('CALLBACK');})
            ->end();
        $this->http['firstNamedServer']->setUp();
        self::assertSame('CALLBACK', $this->http['firstNamedServer']->client->post('/')->send()->getBody(true));
    }

    public function testComplexResponse(): void
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('BODY')
                ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $response = $this->http['firstNamedServer']->client
            ->post('/', ['x-client-header' => 'header-value'], ['post-key' => 'post-value'])->send();
        self::assertSame('BODY', $response->getBody(true));
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Bar', (string) $response->getHeader('X-Foo'));
        self::assertSame('post-value', $this->http['firstNamedServer']->requests->latest()->request->get('post-key'));
    }

    public function testPutRequest(): void
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->methodIs('PUT')
            ->then()
                ->body('BODY')
                ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $response = $this->http['firstNamedServer']->client
            ->put('/', ['x-client-header' => 'header-value'], ['put-key' => 'put-value'])->send();
        self::assertSame('BODY', $response->getBody(true));
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Bar', (string) $response->getHeader('X-Foo'));
        self::assertSame('put-value', $this->http['firstNamedServer']->requests->latest()->request->get('put-key'));
    }

    public function testPostRequest(): void
    {
        $this->http['firstNamedServer']->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('BODY')
            ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http['firstNamedServer']->setUp();
        $response = $this->http['firstNamedServer']->client
            ->post('/', ['x-client-header' => 'header-value'], ['post-key' => 'post-value'])->send();
        self::assertSame('BODY', $response->getBody(true));
        self::assertSame(201, $response->getStatusCode());
        self::assertSame('Bar', (string) $response->getHeader('X-Foo'));
        self::assertSame('post-value', $this->http['firstNamedServer']->requests->latest()->request->get('post-key'));
    }

    public function testFatalError(): void
    {
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            self::markTestSkipped('Comment in to test if fatal errors are properly handled');
        }

        $this->expectException('Error');

        $this->expectExceptionMessage('Cannot instantiate abstract class');
        new TestCase();
    }
}
