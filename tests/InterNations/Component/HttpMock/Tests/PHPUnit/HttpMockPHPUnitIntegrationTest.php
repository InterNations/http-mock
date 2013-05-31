<?php
namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use PHPUnit_Framework_TestCase as TestCase;
use InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;

class HttpMockPHPUnitIntegrationTest extends TestCase
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

        $this->assertSame($path . ' body', (string) $this->http->client->get('/foo')->send()->getBody());

        $request = $this->http->requests->latest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());

        $request = $this->http->requests->at(0);
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame($path, $request->getPath());
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
            $this->assertContains('HTTP mock server error log should be empty', $e->getMessage());
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
}
