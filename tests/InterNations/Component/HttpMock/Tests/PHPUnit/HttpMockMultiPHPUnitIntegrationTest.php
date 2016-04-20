<?php
namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use InterNations\Component\Testing\AbstractTestCase;
use InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;
use Symfony\Component\HttpFoundation\Response;

/** @large */
class HttpMockMultiPHPUnitIntegrationTest extends AbstractTestCase
{
    use HttpMockTrait;

    public static function setUpBeforeClass()
    {
        static::setUpHttpMockBeforeClass(null, null, null, 'special');
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
        $this->http['special']->mock
            ->when()
                ->pathIs($path)
            ->then()
                ->body($path . ' body')
            ->end();
        $this->http['special']->setUp();

        $this->assertSame($path . ' body', (string) $this->http['special']->client->get($path)->send()->getBody());

        $request = $this->http['special']->requests->latest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http['special']->requests->last();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http['special']->requests->first();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http['special']->requests->at(0);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http['special']->requests->pop();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $this->assertSame($path . ' body', (string) $this->http['special']->client->get($path)->send()->getBody());

        $request = $this->http['special']->requests->shift();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $this->setExpectedException(
            'UnexpectedValueException',
            'Expected status code 200 from "/_request/last", got 404'
        );
        $this->http['special']->requests->pop();
    }

    public function testErrorLogOutput()
    {
        $this->http['special']->mock
            ->when()
                ->callback(static function () {error_log('error output');})
            ->then()
            ->end();
        $this->http['special']->setUp();

        $this->http['special']->client->get('/foo')->send();

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
        $response = $this->http['special']->client->get('/foo')->send();
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('No matching expectation found', (string) $response->getBody());
    }

    public function testStopServer()
    {
        $this->http['special']->server->stop();
    }

    /** @depends testStopServer */
    public function testHttpServerIsRestartedIfATestStopsIt()
    {
        $response = $this->http['special']->client->get('/')->send();
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testLimitDurationOfAResponse()
    {
        $this->http['special']->mock
            ->once()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http['special']->setUp();
        $firstResponse = $this->http['special']->client->post('/')->send();
        $this->assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http['special']->client->post('/')->send();
        $this->assertSame(410, $secondResponse->getStatusCode());
        $this->assertSame('Expectation no longer applicable', $secondResponse->getBody(true));

        $this->http['special']->mock
            ->exactly(2)
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http['special']->setUp();
        $firstResponse = $this->http['special']->client->post('/')->send();
        $this->assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http['special']->client->post('/')->send();
        $this->assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http['special']->client->post('/')->send();
        $this->assertSame(410, $thirdResponse->getStatusCode());
        $this->assertSame('Expectation no longer applicable', $thirdResponse->getBody(true));

        $this->http['special']->mock
            ->any()
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('POST METHOD')
            ->end();
        $this->http['special']->setUp();
        $firstResponse = $this->http['special']->client->post('/')->send();
        $this->assertSame(200, $firstResponse->getStatusCode());
        $secondResponse = $this->http['special']->client->post('/')->send();
        $this->assertSame(200, $secondResponse->getStatusCode());
        $thirdResponse = $this->http['special']->client->post('/')->send();
        $this->assertSame(200, $thirdResponse->getStatusCode());
    }

    public function testCallbackOnResponse()
    {
        $this->http['special']->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->callback(static function(Response $response) {$response->setContent('CALLBACK');})
            ->end();
        $this->http['special']->setUp();
        $this->assertSame('CALLBACK', $this->http['special']->client->post('/')->send()->getBody(true));
    }

    public function testComplexResponse()
    {
        $this->http['special']->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('BODY')
                ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http['special']->setUp();
        $response = $this->http['special']->client
            ->post('/', ['x-client-header' => 'header-value'], ['post-key' => 'post-value'])->send();
        $this->assertSame('BODY', $response->getBody(true));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeader('X-Foo'));
        $this->assertSame('post-value', $this->http['special']->requests->latest()->getPostField('post-key'));
    }

    public function testPutRequest()
    {
        $this->http['special']->mock
            ->when()
                ->methodIs('PUT')
            ->then()
                ->body('BODY')
                ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http['special']->setUp();
        $response = $this->http['special']->client
            ->put('/', ['x-client-header' => 'header-value'], ['put-key' => 'put-value'])->send();
        $this->assertSame('BODY', $response->getBody(true));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeader('X-Foo'));
        $this->assertSame('put-value', $this->http['special']->requests->latest()->getPostField('put-key'));
    }

    public function testPostRequest()
    {
        $this->http['special']->mock
            ->when()
                ->methodIs('POST')
            ->then()
                ->body('BODY')
            ->statusCode(201)
                ->header('X-Foo', 'Bar')
            ->end();
        $this->http['special']->setUp();
        $response = $this->http['special']->client
            ->post('/', ['x-client-header' => 'header-value'], ['post-key' => 'post-value'])->send();
        $this->assertSame('BODY', $response->getBody(true));
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Bar', (string) $response->getHeader('X-Foo'));
        $this->assertSame('post-value', $this->http['special']->requests->latest()->getPostField('post-key'));
    }

    public function testFatalError()
    {
        $this->markTestSkipped('Comment in to test if fatal errors are properly handled');
        new \PHPUnit_Framework_TestCase();
    }
}
