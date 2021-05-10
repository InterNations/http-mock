<?php
namespace InterNations\Component\HttpMock\Tests\Request;

use Guzzle\Common\Collection;
use Guzzle\Http\EntityBody;
use Guzzle\Http\Message\Header;
use Guzzle\Http\Message\Header\HeaderCollection;
use Guzzle\Http\QueryString;
use InterNations\Component\HttpMock\Request\UnifiedRequest;
use InterNations\Component\Testing\AbstractTestCase;

class UnifiedRequestTest extends AbstractTestCase
{
    /** @var RequestInterface|MockObject */
    private $wrappedRequest;

    /** @var EntityEnclosingRequestInterface|MockObject */
    private $wrappedEntityEnclosingRequest;

    private UnifiedRequest $unifiedRequest;

    private UnifiedRequest $unifiedEnclosingEntityRequest;

    public function setUp(): void
    {
        $this->wrappedRequest = $this->createMock('Guzzle\Http\Message\RequestInterface');
        $this->wrappedEntityEnclosingRequest = $this->createMock('Guzzle\Http\Message\EntityEnclosingRequestInterface');
        $this->unifiedRequest = new UnifiedRequest($this->wrappedRequest);
        $this->unifiedEnclosingEntityRequest = new UnifiedRequest($this->wrappedEntityEnclosingRequest);
    }

    public static function provideMethods()
    {
        return [
            ['getParams', [], new Collection()],
            ['getHeaders', [], new HeaderCollection()],
            ['getHeaderLines', [], ['Foo' => 'Bar']],
            ['getRawHeaders'],
            ['getQuery', [], new QueryString()],
            ['getMethod'],
            ['getScheme'],
            ['getHost'],
            ['getProtocolVersion'],
            ['getPath'],
            ['getPort', [], 8080],
            ['getUsername'],
            ['getPassword'],
            ['getUrl'],
            ['getCookies', [], []],
            ['getHeader', ['header'], new Header('Foo', 'Bar')],
            ['hasHeader', ['header'], true],
            ['getUrl', [false]],
            ['getUrl', [true]],
            ['getCookie', ['cookieName']],
        ];
    }

    public static function provideEntityEnclosingInterfaceMethods()
    {
        return [
            ['getBody', [], EntityBody::fromString('foo')],
            ['getPostField', ['postField']],
            ['getPostFields', [], new QueryString()],
            ['getPostFiles', [], []],
            ['getPostFile', ['fileName'], []],
        ];
    }

    /** @dataProvider provideMethods */
    public function testMethodsFromRequestInterface($method, array $params = [], $returnValue = 'REQ'): void
    {
        $this->wrappedRequest
            ->expects($this->once())
            ->method($method)
            ->will($this->returnValue($returnValue))
            ->with(...$params);
        $this->assertSame($returnValue, call_user_func_array([$this->unifiedRequest, $method], $params));


        $this->wrappedEntityEnclosingRequest
            ->expects($this->once())
            ->method($method)
            ->will($this->returnValue($returnValue))
            ->with(...$params);
        $this->assertSame(
            $returnValue,
            call_user_func_array([$this->unifiedEnclosingEntityRequest, $method], $params)
        );
    }

    /** @dataProvider provideEntityEnclosingInterfaceMethods */
    public function testEntityEnclosingInterfaceMethods(
        $method,
        array $params = [],
        $returnValue = 'Return Value'
    ): void
    {
        $this->wrappedEntityEnclosingRequest
            ->expects($this->once())
            ->method($method)
            ->will($this->returnValue($returnValue))
            ->with(...$params);

        $this->assertSame(
            $returnValue,
            call_user_func_array([$this->unifiedEnclosingEntityRequest, $method], $params)
        );

        $this->wrappedRequest
            ->expects($this->any())
            ->method('getMethod')
            ->will($this->returnValue('METHOD'));
        $this->wrappedRequest
            ->expects($this->any())
            ->method('getPath')
            ->will($this->returnValue('/foo'));

        $this->expectException('BadMethodCallException');

        $this->expectExceptionMessage(

            sprintf(
                'Cannot call method "%s" on a request that does not enclose an entity. Did you expect a POST/PUT request instead of METHOD /foo?',
                $method
            )

        );
        call_user_func_array([$this->unifiedRequest, $method], $params);
    }

    public function testUserAgent(): void
    {
        $this->assertNull($this->unifiedRequest->getUserAgent());

        $unifiedRequest = new UnifiedRequest($this->wrappedRequest, ['userAgent' => 'UA']);
        $this->assertSame('UA', $unifiedRequest->getUserAgent());
    }
}
