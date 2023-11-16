<?php

namespace InterNations\Component\HttpMock\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InterNations\Component\HttpMock\RequestCollectionFacade;
use InterNations\Component\HttpMock\Tests\Fixtures\Request as TestRequest;
use InterNations\Component\HttpMock\Util;
use InterNations\Component\Testing\AbstractTestCase;
use PHPUnit\Framework\MockObject\MockObject;

class RequestCollectionFacadeTest extends AbstractTestCase
{
    private $client;

    /** @var Request */
    private $request;

    /** @var RequestCollectionFacade */
    private $facade;

    public function setUp() : void
    {
        $this->client = $this->createMock(Client::class);
        $this->facade = new RequestCollectionFacade($this->client);
        $this->request = new Request('GET', '/_request/last');
    }

    public static function provideMethodAndUrls()
    {
        return [
            ['latest', '/_request/last'],
            ['first', '/_request/first'],
            ['last', '/_request/last'],
            ['at', '/_request/0', [0]],
            ['shift', '/_request/first', [], 'delete'],
            ['pop', '/_request/last', [], 'delete'],
        ];
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestingLatestRequest($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createSimpleResponse(), $httpMethod);

        $request = call_user_func_array([$this->facade, $method], $args);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/foo', $request->getUri()->getPath());
        $this->assertSame('RECORDED=1', (string) $request->getBody());
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestLatestResponseWithHttpAuth($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createComplexResponse(), $httpMethod);

        $request = call_user_func_array([$this->facade, $method], $args);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/foo', $request->getUri()->getPath());
        $this->assertSame('RECORDED=1', (string) $request->getBody());
        $this->assertSame('localhost', $request->getUri()->getHost());
        $this->assertSame(1234, $request->getUri()->getPort());
        $this->assertSame('CUSTOM UA', $request->getHeaderLine(('User-Agent')));
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestResponse_InvalidStatusCode($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createResponseWithInvalidStatusCode(), $httpMethod);

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Expected status code 200 from "' . $path . '", got 404');
        call_user_func_array([$this->facade, $method], $args);
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestResponse_EmptyContentType($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createResponseWithEmptyContentType(), $httpMethod);

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Expected content type "text/plain" from "' . $path . '", got ""');
        call_user_func_array([$this->facade, $method], $args);
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestResponse_InvalidContentType($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createResponseWithInvalidContentType(), $httpMethod);

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Expected content type "text/plain" from "' . $path . '", got "text/html"');
        call_user_func_array([$this->facade, $method], $args);
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestResponse_DeserializationError($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createResponseThatCannotBeDeserialized(), $httpMethod);

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Cannot deserialize response from "' . $path . '": "invalid response"');
        call_user_func_array([$this->facade, $method], $args);
    }

    private function mockClient($path, Response $response, $method)
    {
        $this->client
            ->expects($this->once())
            ->method($method)
            ->with($path)
            ->will($this->returnValue($response));
    }

    private function createSimpleResponse()
    {
        $recordedRequest = new TestRequest(
            'POST',
            'http://localhost/foo',
            [],
            'RECORDED=1'
        );

        return new Response(
            200,
            ['Content-Type' => 'text/plain'],
            serialize(
                [
                    'server' => [],
                    'request' => Util::serializePsrMessage($recordedRequest),
                ]
            )
        );
    }

    private function createComplexResponse()
    {
        $recordedRequest = new TestRequest(
            'POST',
            'http://localhost:1234/foo',
            [
                'Php-Auth-User' => 'username',
                'Php-Auth-Pw' => 'password',
                'User-Agent' => 'CUSTOM UA',
            ],
            'RECORDED=1'
        );

        return new Response(
            200,
            ['Content-Type' => 'text/plain; charset=UTF-8'],
            serialize(
                [
                    'request' => Util::serializePsrMessage($recordedRequest),
                ]
            )
        );
    }

    private function createResponseWithInvalidStatusCode()
    {
        return new Response(404);
    }

    private function createResponseWithInvalidContentType()
    {
        return new Response(200, ['Content-Type' => 'text/html']);
    }

    private function createResponseWithEmptyContentType()
    {
        return new Response(200, []);
    }

    private function createResponseThatCannotBeDeserialized()
    {
        return new Response(200, ['Content-Type' => 'text/plain'], 'invalid response');
    }
}
