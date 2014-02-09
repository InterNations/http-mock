<?php
namespace InterNations\Component\HttpMock\Tests\Builder;

use InterNations\Component\HttpMock\Builder\ResponseBuilder;
use InterNations\Component\Testing\AbstractTestCase;

class ResponseBuilderTest extends AbstractTestCase
{
    private $mockBuilder;

    /** @var ResponseBuilder */
    private $responseBuilder;

    public function setUp()
    {
        $this->mockBuilder = $this->getSimpleMock('InterNations\Component\HttpMock\Builder\MockBuilder');
        $this->responseBuilder = new ResponseBuilder($this->mockBuilder);
    }

    public function testDefault()
    {
        $response = $this->responseBuilder->getResponse();
        $this->assertNull($response->getCallback());
    }

    public function testBody()
    {
        $this->responseBuilder->body('body');
        $this->assertSame('body', $this->responseBuilder->getResponse()->getContent());
    }

    public function testHeader()
    {
        $this->responseBuilder->header('X-foo', 'bar');
        $this->assertSame('bar', $this->responseBuilder->getResponse()->headers->get('X-foo'));
    }

    public function testCallback()
    {
        $callback = static function() {};
        $this->responseBuilder->callback($callback);
        $this->assertSame($callback, $this->responseBuilder->getResponse()->getCallback()->getClosure());
    }

    public function testStatusCode()
    {
        $this->responseBuilder->statusCode(201);
        $this->assertSame(201, $this->responseBuilder->getResponse()->getStatusCode());
    }
}
