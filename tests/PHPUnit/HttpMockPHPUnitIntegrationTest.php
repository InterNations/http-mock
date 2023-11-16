<?php

namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use Fig\Http\Message\StatusCodeInterface;
use GuzzleHttp\Psr7\Utils;
use InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;
use InterNations\Component\Testing\AbstractTestCase;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/** @large */
class HttpMockPHPUnitIntegrationTest extends AbstractTestCase
{
    use HttpMockTrait;

    public static function setUpBeforeClass() : void
    {
        static::setUpHttpMockBeforeClass();
    }

    public static function tearDownAfterClass() : void
    {
        static::tearDownHttpMockAfterClass();
    }

    public function setUp() : void
    {
        $this->setUpHttpMock();
    }

    public function tearDown() : void
    {
        $this->tearDownHttpMock();
    }

    public static function getPaths()
    {
        return [
            [
                '/foo',
                '/bar',
            ],
        ];
    }

    /** @dataProvider getPaths */
    public function testSimpleRequest($path)
    {
        $this->http->mock
            ->when()
                ->pathIs($path)
            ->then()
                ->body($path . ' body')
            ->end();
        $this->http->setUp();

        $this->assertSame($path . ' body', (string) $this->http->client->get($path)->getBody());

        $request = $this->http->requests->latest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());

        $request = $this->http->requests->last();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());

        $request = $this->http->requests->first();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());

        $request = $this->http->requests->at(0);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());

        $request = $this->http->requests->pop();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());

        $this->assertSame($path . ' body', (string) $this->http->client->get($path)->getBody());

        $request = $this->http->requests->shift();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getUri()->getPath());

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Expected status code 200 from "/_request/last", got 404');
        $this->http->requests->pop();
    }

    public function testErrorLogOutput()
    {
        $this->http->mock
            ->when()
                ->callback(static function () {error_log('error output'); })
            ->then()
            ->end();
        $this->http->setUp();

        $this->http->client->get('/foo');

        // Should fail during tear down as we have an error_log() on the server side
        try {
            $this->tearDown();
            $this->fail('Exception expected');
        } catch (\Exception $e) {
            $this->assertStringContainsString('HTTP mock server standard error output should be empty', $e->getMessage());
        }
    }

    public function testFailedRequest()
    {
        $response = $this->http->client->get('/foo');
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('No matching expectation found', (string) $response->getBody());
    }

    public function testStopServer()
    {
        $this->http->server->stop();
    }

    /** @depends testStopServer */
    public function testHttpServerIsRestartedIfATestStopsIt()
    {
        $response = $this->http->client->get('/');
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testLimitDurationOfAResponse()
    {
        $this->http->mock
            ->once()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http->setUp();
        $firstResponse = $this->http->client->post('/');
        $this->assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http->client->post('/');
        $this->assertSame(410, $secondResponse->getStatusCode());
        $this->assertSame('Expectation no longer applicable', (string) $secondResponse->getBody());

        $this->http->mock
            ->exactly(2)
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http->setUp();
        $firstResponse = $this->http->client->post('/');
        $this->assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http->client->post('/');
        $this->assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http->client->post('/');
        $this->assertSame(410, $thirdResponse->getStatusCode());
        $this->assertSame('Expectation no longer applicable', (string) $thirdResponse->getBody());

        $this->http->mock
            ->any()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http->setUp();
        $firstResponse = $this->http->client->post('/');
        $this->assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http->client->post('/');
        $this->assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http->client->post('/');
        $this->assertSame(200, $thirdResponse->getStatusCode());
    }

    public function testCallbackOnResponse()
    {
        $this->http->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->callback(static function (Response $response) {
                    return $response->withBody(Utils::streamFor('CALLBACK'));
                })
            ->end();
        $this->http->setUp();
        $this->assertSame('CALLBACK', (string) $this->http->client->post('/')->getBody());
    }

    public function testComplexResponse()
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
        $response = $this->http->client
            ->post('/', [
                'headers' => ['x-client-header' => 'header-value'],
                'form_params' => ['post-key' => 'post-value'],
            ]);
        $this->assertSame('BODY', (string) $response->getBody());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeaderLine('X-Foo'));
        parse_str($this->http->requests->latest()->getBody()->getContents(), $body);
        $this->assertSame('post-value', $body['post-key']);
    }

    public function testPutRequest()
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
        $response = $this->http->client
            ->put('/', [
                'headers' => ['x-client-header' => 'header-value'],
                'form_params' => ['put-key' => 'put-value'],
            ]);
        $this->assertSame('BODY', (string) $response->getBody());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeaderLine('X-Foo'));
        parse_str($this->http->requests->latest()->getBody()->getContents(), $body);
        $this->assertSame('put-value', $body['put-key']);
    }

    public function testPostRequest()
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
        $response = $this->http->client
            ->post('/', [
                'headers' => ['x-client-header' => 'header-value'],
                'form_params' => ['post-key' => 'post-value'],
            ]);
        $this->assertSame('BODY', (string) $response->getBody());
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeaderLine('X-Foo'));
        parse_str($this->http->requests->latest()->getBody()->getContents(), $body);
        $this->assertSame('post-value', $body['post-key']);
    }

    public function testCountRequests()
    {
        $this->http->mock
            ->when()
                ->pathIs('/resource')
            ->then()
                ->body('resource body')
            ->end();
        $this->http->setUp();

        $this->assertCount(0, $this->http->requests);
        $this->assertSame('resource body', (string) $this->http->client->get('/resource')->getBody());
        $this->assertCount(1, $this->http->requests);
    }

    public function testMatchQueryString()
    {
        $this->http->mock
            ->when()
                ->callback(
                    function (Request $request) {
                        parse_str($request->getUri()->getQuery(), $query);

                        return isset($query['key1']);
                    }
                )
                ->methodIs('GET')
            ->then()
                ->body('query string')
            ->end();
        $this->http->setUp();

        $this->assertSame('query string', (string) $this->http->client->get('/?key1=')->getBody());

        $this->assertEquals(StatusCodeInterface::STATUS_NOT_FOUND, (string) $this->http->client->get('/')->getStatusCode());
        $this->assertEquals(StatusCodeInterface::STATUS_NOT_FOUND, (string) $this->http->client->post('/')->getStatusCode());
    }

    public function testMatchRegex()
    {
        $this->http->mock
            ->when()
                ->methodIs($this->http->matches->regex('/(GET|POST)/'))
            ->then()
                ->body('response')
            ->end();
        $this->http->setUp();

        $this->assertSame('response', (string) $this->http->client->get('/')->getBody());
        $this->assertSame('response', (string) $this->http->client->get('/')->getBody());
    }

    public function testMatchQueryParams()
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

        $this->assertSame(
            'response',
            (string) $this->http->client->get('/?p1=&p2=v2&p4=any&p5=v5&p6=v6')->getBody()
        );
        $this->assertEquals(
            StatusCodeInterface::STATUS_NOT_FOUND,
            (string) $this->http->client->get('/?p1=&p2=v2&p3=foo')->getStatusCode()
        );
        $this->assertEquals(
            StatusCodeInterface::STATUS_NOT_FOUND,
            (string) $this->http->client->get('/?p1=')->getStatusCode()
        );
        $this->assertEquals(
            StatusCodeInterface::STATUS_NOT_FOUND,
            (string) $this->http->client->get('/?p3=foo')->getStatusCode()
        );
    }

    public function testFatalError()
    {
        if (version_compare(PHP_VERSION, '7.0', '<')) {
            $this->markTestSkipped('Comment in to test if fatal errors are properly handled');
        }

        $this->expectException('Error');

        $this->expectExceptionMessage('Cannot instantiate abstract class');
        new TestCase();
    }
}
