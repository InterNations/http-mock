<?php
namespace InterNations\Component\HttpMock\Tests;

use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use InterNations\Component\HttpMock\RequestCollectionFacade;
use InterNations\Component\Testing\AbstractTestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use InterNations\Component\HttpMock\Tests\Fixtures\Request as TestRequest;

class RequestCollectionFacadeTest extends AbstractTestCase
{
    /** @var ClientInterface|MockObject */
    private $client;

    /** @var Request */
    private $request;

    /** @var RequestCollectionFacade */
    private $facade;

    public function setUp()
    {
        $this->client = $this->createMock('Guzzle\Http\ClientInterface');
        $this->facade = new RequestCollectionFacade($this->client);
        $this->request = new Request('GET', '/_request/last');
        $this->request->setClient($this->client);
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
        $this->assertSame('/foo', $request->getPath());
        $this->assertSame('RECORDED=1', (string) $request->getBody());
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestLatestResponseWithHttpAuth($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createComplexResponse(), $httpMethod);

        $request = call_user_func_array([$this->facade, $method], $args);

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/foo', $request->getPath());
        $this->assertSame('RECORDED=1', (string) $request->getBody());
        $this->assertSame('host', $request->getHost());
        $this->assertSame(1234, $request->getPort());
        $this->assertSame('username', $request->getUsername());
        $this->assertSame('password', $request->getPassword());
        $this->assertSame('CUSTOM UA', $request->getUserAgent());
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestResponse_InvalidStatusCode($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createResponseWithInvalidStatusCode(), $httpMethod);

        $this->setExpectedException(
            'UnexpectedValueException',
            'Expected status code 200 from "' . $path . '", got 404'
        );
        call_user_func_array([$this->facade, $method], $args);
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestResponse_EmptyContentType($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createResponseWithEmptyContentType(), $httpMethod);

        $this->setExpectedException(
            'UnexpectedValueException',
            'Expected content type "text/plain" from "' . $path . '", got ""'
        );
        call_user_func_array([$this->facade, $method], $args);
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestResponse_InvalidContentType($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createResponseWithInvalidContentType(), $httpMethod);

        $this->setExpectedException(
            'UnexpectedValueException',
            'Expected content type "text/plain" from "' . $path . '", got "text/html"'
        );
        call_user_func_array([$this->facade, $method], $args);
    }

    /** @dataProvider provideMethodAndUrls */
    public function testRequestResponse_DeserializationError($method, $path, array $args = [], $httpMethod = 'get')
    {
        $this->mockClient($path, $this->createResponseThatCannotBeDeserialized(), $httpMethod);

        $this->setExpectedException(
            'UnexpectedValueException',
            'Cannot deserialize response from "' . $path . '": "invalid response"'
        );
        call_user_func_array([$this->facade, $method], $args);
    }

    private function mockClient($path, Response $response, $method)
    {
        $this->client
            ->expects($this->once())
            ->method($method)
            ->with($path)
            ->will($this->returnValue($this->request));

        $this->client
            ->expects($this->once())
            ->method('send')
            ->with($this->request)
            ->will($this->returnValue($response));
    }

    private function createSimpleResponse()
    {
        $recordedRequest = new TestRequest();
        $recordedRequest->setMethod('POST');
        $recordedRequest->setRequestUri('/foo');
        $recordedRequest->setContent('RECORDED=1');

        return new Response(
            '200',
            ['Content-Type' => 'text/plain'],
            serialize(
                [
                    'server' => [],
                    'request' => (string) $recordedRequest,
                ]
            )
        );
    }

    private function createComplexResponse()
    {
        $recordedRequest = new TestRequest();
        $recordedRequest->setMethod('POST');
        $recordedRequest->setRequestUri('/foo');
        $recordedRequest->setContent('RECORDED=1');
        $recordedRequest->headers->set('Php-Auth-User', 'ignored');
        $recordedRequest->headers->set('Php-Auth-Pw', 'ignored');
        $recordedRequest->headers->set('User-Agent', 'ignored');

        return new Response(
            '200',
            ['Content-Type' => 'text/plain; charset=UTF-8'],
            serialize(
                [
                    'server' => [
                        'HTTP_HOST'       => 'host',
                        'HTTP_PORT'       => 1234,
                        'PHP_AUTH_USER'   => 'username',
                        'PHP_AUTH_PW'     => 'password',
                        'HTTP_USER_AGENT' => 'CUSTOM UA',
                    ],
                    'request' => (string) $recordedRequest,
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
