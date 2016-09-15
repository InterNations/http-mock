<?php
namespace InterNations\Component\HttpMock\Tests\Request;

use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use InterNations\Component\HttpMock\Request\UnifiedRequest;
use InterNations\Component\Testing\AbstractTestCase;
use Guzzle\Http\Message\RequestInterface;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use PHPUnit_Framework_MockObject_Matcher_Parameters as ParametersMatcher;

class UnifiedRequestTest extends AbstractTestCase
{
    /** @var RequestInterface|MockObject */
    private $wrappedRequest;

    /** @var EntityEnclosingRequestInterface|MockObject */
    private $wrappedEntityEnclosingRequest;

    /** @var UnifiedRequest */
    private $unifiedRequest;

    /** @var UnifiedRequest */
    private $unifiedEnclosingEntityRequest;

    public function setUp()
    {
        $this->wrappedRequest = $this->createMock('Guzzle\Http\Message\RequestInterface');
        $this->wrappedEntityEnclosingRequest = $this->createMock('Guzzle\Http\Message\EntityEnclosingRequestInterface');
        $this->unifiedRequest = new UnifiedRequest($this->wrappedRequest);
        $this->unifiedEnclosingEntityRequest = new UnifiedRequest($this->wrappedEntityEnclosingRequest);
    }

    public static function provideMethods()
    {
        return [
            ['getParams'],
            ['getHeaders'],
            ['getHeaderLines'],
            ['getRawHeaders'],
            ['getQuery'],
            ['getMethod'],
            ['getScheme'],
            ['getHost'],
            ['getProtocolVersion'],
            ['getPath'],
            ['getPort'],
            ['getUsername'],
            ['getPassword'],
            ['getUrl'],
            ['getCookies'],
            ['getHeader', ['header']],
            ['hasHeader', ['header']],
            ['getUrl', [false]],
            ['getUrl', [true]],
            ['getCookie', ['cookieName']],
        ];
    }

    public static function provideEntityEnclosingInterfaceMethods()
    {
        return [
            ['getBody'],
            ['getPostField', ['postField']],
            ['getPostFields'],
            ['getPostFiles'],
            ['getPostFile', ['fileName']],
        ];
    }

    /** @dataProvider provideMethods */
    public function testMethodsFromRequestInterface($method, array $params = [])
    {
        $this->wrappedRequest
            ->expects($this->once())
            ->method($method)
            ->will($this->returnValue('REQ'))
            ->getMatcher()->parametersMatcher = new ParametersMatcher($params);
        $this->assertSame('REQ', call_user_func_array([$this->unifiedRequest, $method], $params));


        $this->wrappedEntityEnclosingRequest
            ->expects($this->once())
            ->method($method)
            ->will($this->returnValue('ENTITY_ENCL_REQ'))
            ->getMatcher()->parametersMatcher = new ParametersMatcher($params);
        $this->assertSame(
            'ENTITY_ENCL_REQ',
            call_user_func_array([$this->unifiedEnclosingEntityRequest, $method], $params)
        );
    }

    /** @dataProvider provideEntityEnclosingInterfaceMethods */
    public function testEntityEnclosingInterfaceMethods($method, array $params = [])
    {
        $this->wrappedEntityEnclosingRequest
            ->expects($this->once())
            ->method($method)
            ->will($this->returnValue('ENTITY_ENCL_REQ'))
            ->getMatcher()->parametersMatcher = new ParametersMatcher($params);

        $this->assertSame(
            'ENTITY_ENCL_REQ',
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

        $this->setExpectedException(
            'BadMethodCallException',
            sprintf(
                'Cannot call method "%s" on a request that does not enclose an entity. Did you expect a POST/PUT request instead of METHOD /foo?',
                $method
            )
        );
        call_user_func_array([$this->unifiedRequest, $method], $params);
    }

    public function testUserAgent()
    {
        $this->assertNull($this->unifiedRequest->getUserAgent());

        $unifiedRequest = new UnifiedRequest($this->wrappedRequest, ['userAgent' => 'UA']);
        $this->assertSame('UA', $unifiedRequest->getUserAgent());
    }
}
