<?php
namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use InterNations\Component\HttpMock\Tests\TestCase;
use InterNations\Component\HttpMock\PHPUnit\HttpMock;

/** @large */
class HttpMockPHPUnitIntegrationBasePathTest extends TestCase
{
    use HttpMock;

    public static function setUpBeforeClass(): void
    {
        static::setUpHttpMockBeforeClass(null, null, '/custom-base-path');
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

    public function testSimpleRequest(): void
    {
        $this->http->mock
            ->when()
                ->pathIs('/foo')
            ->then()
                ->body('/foo' . ' body')
            ->end();
        $this->http->setUp();

        self::assertSame(
            '/foo body',
            (string) $this->http->client->sendRequest(
                $this->getRequestFactory()->createRequest('GET', '/custom-base-path/foo')
            )->getBody()
        );

        $request = $this->http->requests->latest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/custom-base-path/foo', $request->getRequestUri());
    }
}
