<?php
namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;
use InterNations\Component\Testing\AbstractTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/** @large */
class HttpMockPHPUnitIntegrationTest extends AbstractTestCase
{
    use HttpMockTrait;

    public static function setUpBeforeClass()
    {
        static::setUpHttpMockBeforeClass();
    }

    public static function tearDownAfterClass()
    {
        static::tearDownHttpMockAfterClass();
    }

    public function setUp()
    {
        $this->setUpHttpMock();
    }

    public function tearDown()
    {
        $this->tearDownHttpMock();
    }

    public static function getPaths()
    {
        return [
            [
                '/foo',
                '/bar',
            ]
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

        $this->assertSame($path . ' body', (string) $this->http->client->get($path)->send()->getBody());

        $request = $this->http->requests->latest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http->requests->last();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http->requests->first();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http->requests->at(0);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http->requests->pop();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $this->assertSame($path . ' body', (string) $this->http->client->get($path)->send()->getBody());

        $request = $this->http->requests->shift();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $this->setExpectedException(
            'UnexpectedValueException',
            'Expected status code 200 from "/_request/last", got 404'
        );
        $this->http->requests->pop();
    }

    public function testErrorLogOutput()
    {
        $this->http->mock
            ->when()
                ->callback(static function () {error_log('error output');})
            ->then()
            ->end();
        $this->http->setUp();

        $this->http->client->get('/foo')->send();

        // Should fail during tear down as we have an error_log() on the server side
        try {
            $this->tearDown();
            $this->fail('Exception expected');
        } catch (\PHPUnit_Framework_ExpectationFailedException $e) {
            $this->assertContains('HTTP mock server standard error output should be empty', $e->getMessage());
        }
    }

    public function testFailedRequest()
    {
        $response = $this->http->client->get('/foo')->send();
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
        $response = $this->http->client->get('/')->send();
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
        $firstResponse = $this->http->client->post('/')->send();
        $this->assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http->client->post('/')->send();
        $this->assertSame(410, $secondResponse->getStatusCode());
        $this->assertSame('Expectation no longer applicable', $secondResponse->getBody(true));

        $this->http->mock
            ->exactly(2)
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http->setUp();
        $firstResponse = $this->http->client->post('/')->send();
        $this->assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http->client->post('/')->send();
        $this->assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http->client->post('/')->send();
        $this->assertSame(410, $thirdResponse->getStatusCode());
        $this->assertSame('Expectation no longer applicable', $thirdResponse->getBody(true));

        $this->http->mock
            ->any()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http->setUp();
        $firstResponse = $this->http->client->post('/')->send();
        $this->assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http->client->post('/')->send();
        $this->assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http->client->post('/')->send();
        $this->assertSame(200, $thirdResponse->getStatusCode());
    }

    public function testCallbackOnResponse()
    {
        $this->http->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->callback(static function(Response $response) {$response->setContent('CALLBACK');})
            ->end();
        $this->http->setUp();
        $this->assertSame('CALLBACK', $this->http->client->post('/')->send()->getBody(true));
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
            ->post('/', ['x-client-header' => 'header-value'], ['post-key' => 'post-value'])->send();
        $this->assertSame('BODY', $response->getBody(true));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeader('X-Foo'));
        $this->assertSame('post-value', $this->http->requests->latest()->getPostField('post-key'));
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
            ->put('/', ['x-client-header' => 'header-value'], ['put-key' => 'put-value'])->send();
        $this->assertSame('BODY', $response->getBody(true));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeader('X-Foo'));
        $this->assertSame('put-value', $this->http->requests->latest()->getPostField('put-key'));
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
            ->post('/', ['x-client-header' => 'header-value'], ['post-key' => 'post-value'])->send();
        $this->assertSame('BODY', $response->getBody(true));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeader('X-Foo'));
        $this->assertSame('post-value', $this->http->requests->latest()->getPostField('post-key'));
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
        $this->assertSame('resource body', (string) $this->http->client->get('/resource')->send()->getBody());
        $this->assertCount(1, $this->http->requests);
    }

    public function testMatchQueryString()
    {
        $this->http->mock
            ->when()
                ->callback(
                    function (Request $request) {
                        return $request->query->has('key1');
                    }
                )
                ->methodIs('GET')
            ->then()
                ->body('query string')
            ->end();
        $this->http->setUp();

        $this->assertSame('query string', (string) $this->http->client->get('/?key1=')->send()->getBody());

        $this->assertEquals(Response::HTTP_NOT_FOUND, (string) $this->http->client->get('/')->send()->getStatusCode());
        $this->assertEquals(Response::HTTP_NOT_FOUND, (string) $this->http->client->post('/')->send()->getStatusCode());
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

        $this->assertSame('response', (string) $this->http->client->get('/')->send()->getBody());
        $this->assertSame('response', (string) $this->http->client->get('/')->send()->getBody());
    }

    public function testMatchQueryParams()
    {
        $this->http->mock
            ->when()
                ->queryParamExists('p1')
                ->queryParamIs('p2', 'v2')
                ->queryParamNotExists('p3')
            ->then()
                ->body('response')
            ->end();
        $this->http->setUp();

        $this->assertSame('response', (string) $this->http->client->get('/?p1=&p2=v2')->send()->getBody());
        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            (string) $this->http->client->get('/?p1=&p2=v2&p3=foo')->send()->getStatusCode()
        );
        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            (string) $this->http->client->get('/?p1=')->send()->getStatusCode()
        );
        $this->assertEquals(
            Response::HTTP_NOT_FOUND,
            (string) $this->http->client->get('/?p3=foo')->send()->getStatusCode()
        );
    }

    public function testFatalError()
    {
        $this->markTestSkipped('Comment in to test if fatal errors are properly handled');
        new \PHPUnit_Framework_TestCase();
    }
}
