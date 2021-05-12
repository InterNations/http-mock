<?php
namespace InterNations\Component\HttpMock\Tests;

use GuzzleHttp\Client;
use InterNations\Component\HttpMock\RequestCollectionFacade;
use InterNations\Component\HttpMock\Tests\Fixtures\Request as TestRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use function serialize;

class RequestCollectionFacadeTest extends TestCase
{
    /** @var Client|MockObject */
    private $client;

    private RequestCollectionFacade $facade;

    public function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->facade = new RequestCollectionFacade($this->client, $this->getRequestFactory());
    }

    /** @return array<array{0:string,1:string,2:array<mixed>,3:string}> */
    public static function getMethodAndUrls(): array
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

    /**
     * @dataProvider getMethodAndUrls
     * @param array<mixed> $args
     */
    public function testRequestingLatestRequest(
        string $method,
        string $path,
        array $args = [],
        string $httpMethod = 'get'
    ): void
    {
        $this->mockClient($path, $this->createSimpleResponse(), $httpMethod);

        /** @var \Symfony\Component\HttpFoundation\Request $request */
        $request = $this->facade->{$method}(...$args);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/foo', $request->getRequestUri());
        self::assertSame('RECORDED=1', $request->getContent());
    }

    /**
     * @dataProvider getMethodAndUrls
     * @param array<mixed> $args
     */
    public function testRequestLatestResponseWithHttpAuth(
        string $method,
        string $path,
        array $args = [],
        string $httpMethod = 'get'
    ): void
    {
        $this->mockClient($path, $this->createComplexResponse(), $httpMethod);

        /** @var \Symfony\Component\HttpFoundation\Request $request */
        $request = $this->facade->{$method}(...$args);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/foo', $request->getRequestUri());
        self::assertSame('RECORDED=1', $request->getContent());
        self::assertSame('host', $request->getHost());
        self::assertSame(1234, $request->getPort());
        self::assertSame('username', $request->getUser());
        self::assertSame('password', $request->getPassword());
        self::assertSame('CUSTOM UA', $request->headers->get('User-Agent'));
    }

    /**
     * @dataProvider getMethodAndUrls
     * @param array<mixed> $args
     */
    public function testRequestResponseWithInvalidStatusCode(
        string $method,
        string $path,
        array $args = [],
        string $httpMethod = 'get'
    ): void
    {
        $this->mockClient($path, $this->createResponseWithInvalidStatusCode(), $httpMethod);

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Expected status code 200 from "' . $path . '", got 404');
        $this->facade->{$method}(...$args);
    }

    /**
     * @dataProvider getMethodAndUrls
     * @param array<mixed> $args
     */
    public function testRequestResponseWithEmptyContentType(
        string $method,
        string $path,
        array $args = [],
        string $httpMethod = 'get'
    ): void
    {
        $this->mockClient($path, $this->createResponseWithEmptyContentType(), $httpMethod);

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Expected content type "text/plain" from "' . $path . '", got ""');
        $this->facade->{$method}(...$args);
    }

    /**
     * @dataProvider getMethodAndUrls
     * @param array<mixed> $args
     */
    public function testRequestResponseWithInvalidContentType(
        string $method,
        string $path,
        array $args = [],
        string $httpMethod = 'get'
    ): void
    {
        $this->mockClient($path, $this->createResponseWithInvalidContentType(), $httpMethod);

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Expected content type "text/plain" from "' . $path . '", got "text/html"');
        $this->facade->{$method}(...$args);
    }

    /**
     * @dataProvider getMethodAndUrls
     * @param array<mixed> $args
     */
    public function testRequestResponseWithDeserializationError(
        string $method,
        string $path,
        array $args = [],
        string $httpMethod = 'get'
    ): void
    {
        $this->mockClient($path, $this->createResponseThatCannotBeDeserialized(), $httpMethod);

        $this->expectException('UnexpectedValueException');

        $this->expectExceptionMessage('Cannot deserialize response from "' . $path . '": "invalid response"');
        $this->facade->{$method}(...$args);
    }

    private function mockClient(string $path, ResponseInterface $response, string $method): void
    {
        $this->client
            ->expects(self::once())
            ->method('sendRequest')
            ->with($this->getRequestFactory()->createRequest($method, $path))
            ->willReturn($response);
    }

    private function createSimpleResponse(): ResponseInterface
    {
        $recordedRequest = new TestRequest();
        $recordedRequest->setMethod('POST');
        $recordedRequest->setRequestUri('/foo');
        $recordedRequest->setContent('RECORDED=1');

        return $this->getResponseFactory()
            ->createResponse()
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->getStreamFactory()->createStream(serialize($recordedRequest)));
    }

    private function createComplexResponse(): ResponseInterface
    {
        $recordedRequest = new TestRequest();
        $recordedRequest->setMethod('POST');
        $recordedRequest->setRequestUri('/foo');
        $recordedRequest->setContent('RECORDED=1');
        $recordedRequest->server->set('SERVER_NAME', 'host');
        $recordedRequest->server->set('SERVER_PORT', 1234);
        $recordedRequest->headers->set('Php-Auth-User', 'username');
        $recordedRequest->headers->set('Php-Auth-Pw', 'password');
        $recordedRequest->headers->set('User-Agent', 'CUSTOM UA');


        return $this->getResponseFactory()
            ->createResponse()
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withBody($this->getStreamFactory()->createStream(serialize($recordedRequest)));
    }

    private function createResponseWithInvalidStatusCode(): ResponseInterface
    {
        return $this->getResponseFactory()
            ->createResponse(404);
    }

    private function createResponseWithInvalidContentType(): ResponseInterface
    {
        return $this->getResponseFactory()
            ->createResponse()
            ->withHeader('Content-Type', 'text/html');
    }

    private function createResponseWithEmptyContentType(): ResponseInterface
    {
        return $this->getResponseFactory()
            ->createResponse();
    }

    private function createResponseThatCannotBeDeserialized(): ResponseInterface
    {
        return $this->getResponseFactory()
            ->createResponse()
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->withBody($this->getStreamFactory()->createStream('invalid response'));
    }
}
