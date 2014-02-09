<?php
namespace InterNations\Component\HttpMock\Tests\Builder;

use InterNations\Component\HttpMock\Builder\ResponseBuilder;
use InterNations\Component\HttpMock\Builder\StubBuilder;
use InterNations\Component\Testing\AbstractTestCase;
use PHPUnit_Framework_MockObject_MockObject;

class StubBuilderTest extends AbstractTestCase
{
    /** @var ResponseBuilder|PHPUnit_Framework_MockObject_MockObject */
    private $responseBuilder;

    /** @var StubBuilder */
    private $stubBuilder;

    public function setUp()
    {
        $this->responseBuilder = $this->getSimpleMock('InterNations\Component\HttpMock\Builder\ResponseBuilder');
        $this->stubBuilder = new StubBuilder($this->responseBuilder);
    }

    public function testDefault()
    {
        $response = $this->stubBuilder->getResponse();
        $this->assertNull($response->getCallback());
    }

    public function testBody()
    {
        $this->stubBuilder->body('body');
        $this->assertSame('body', $this->stubBuilder->getResponse()->getContent());
    }

    public function testHeader()
    {
        $this->stubBuilder->header('X-foo', 'bar');
        $this->assertSame('bar', $this->stubBuilder->getResponse()->headers->get('X-foo'));
    }

    public function testCallback()
    {
        $callback = static function() {};
        $this->stubBuilder->callback($callback);
        $this->assertSame($callback, $this->stubBuilder->getResponse()->getCallback()->getClosure());
    }

    public function testStatusCode()
    {
        $this->stubBuilder->statusCode(201);
        $this->assertSame(201, $this->stubBuilder->getResponse()->getStatusCode());
    }

    public function testEnd()
    {
        $this->assertSame($this->responseBuilder, $this->stubBuilder->end());
    }
}
