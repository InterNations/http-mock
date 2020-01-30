<?php

namespace InterNations\Component\HttpMock\Tests\PHPUnit;

use InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;
use InterNations\Component\Testing\AbstractTestCase;

/** @large */
class HttpMockPHPUnitIntegrationBasePathTest extends AbstractTestCase
{
    use HttpMockTrait;

    public static function setUpBeforeClass()
    {
        static::setUpHttpMockBeforeClass(null, null, '/custom-base-path');
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

    public function testSimpleRequest()
    {
        $this->http->mock
            ->when()
                ->pathIs('/foo')
            ->then()
                ->body('/foo' . ' body')
            ->end();
        $this->http->setUp();

        $this->assertSame('/foo body', (string) $this->http->client->get('/custom-base-path/foo')->getBody());

        $request = $this->http->requests->latest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/custom-base-path/foo', $request->getUri()->getPath());
    }
}
