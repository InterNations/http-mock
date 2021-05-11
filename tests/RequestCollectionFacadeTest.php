<?php
namespace InterNations\Component\HttpMock\Tests;

use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use InterNations\Component\HttpMock\RequestCollectionFacade;
use InterNations\Component\Testing\AbstractTestCase;
use InterNations\Component\HttpMock\Tests\Fixtures\Request as TestRequest;
use PHPUnit\Framework\MockObject\MockObject;

class RequestCollectionFacadeTest extends AbstractTestCase
{
    /** @var ClientInterface|MockObject */
    private $client;

    private Request $request;

    private RequestCollectionFacade $facade;

    public function setUp(): void
    {
        $this->client = $this->createMock(ClientInterface::class);
        $this->facade = new RequestCollectionFacade($this->client);
        $this->request = new Request('GET', '/_request/last');
        $this->request->setClient($this->client);
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

        $request = call_user_func_array([$this->facade, $method], $args);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/foo', $request->getPath());
        self::assertSame('RECORDED=1', (string) $request->getBody());
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

        $request = call_user_func_array([$this->facade, $method], $args);

        self::assertSame('POST', $request->getMethod());
        self::assertSame('/foo', $request->getPath());
        self::assertSame('RECORDED=1', (string) $request->getBody());
        self::assertSame('host', $request->getHost());
        self::assertSame(1234, $request->getPort());
        self::assertSame('username', $request->getUsername());
        self::assertSame('password', $request->getPassword());
        self::assertSame('CUSTOM UA', $request->getUserAgent());
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
        call_user_func_array([$this->facade, $method], $args);
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
        call_user_func_array([$this->facade, $method], $args);
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
        call_user_func_array([$this->facade, $method], $args);
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
        call_user_func_array([$this->facade, $method], $args);
    }

    private function mockClient(string $path, Response $response, string $method): void
    {
        $this->client
            ->expects(self::once())
            ->method($method)
            ->with($path)
            ->willReturn($this->request);

        $this->client
            ->expects(self::once())
            ->method('send')
            ->with($this->request)
            ->willReturn($response);
    }

    private function createSimpleResponse(): Response
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

    private function createComplexResponse(): Response
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

    private function createResponseWithInvalidStatusCode(): Response
    {
        return new Response(404);
    }

    private function createResponseWithInvalidContentType(): Response
    {
        return new Response(200, ['Content-Type' => 'text/html']);
    }

    private function createResponseWithEmptyContentType(): Response
    {
        return new Response(200, []);
    }

    private function createResponseThatCannotBeDeserialized(): Response
    {
        return new Response(200, ['Content-Type' => 'text/plain'], 'invalid response');
    }
}
