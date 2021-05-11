<?php
namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use InterNations\Component\Testing\AbstractTestCase;
use InterNations\Component\HttpMock\PHPUnit\HttpMock;

/** @large */
class HttpMockPHPUnitIntegrationBasePathTest extends AbstractTestCase
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

        $this->assertSame('/foo body', (string) $this->http->client->get('/custom-base-path/foo')->send()->getBody());

        $request = $this->http->requests->latest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/custom-base-path/foo', $request->getRequestUri());
    }
}
