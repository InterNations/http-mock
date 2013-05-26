<?php
namespace InterNations\Component\HttpMock\Tests;

use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\EntityEnclosingRequest;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\Response;
use InterNations\Component\HttpMock\RequestCollectionFacade;
use InterNations\Component\Testing\AbstractTestCase;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use InterNations\Component\HttpMock\Tests\Fixtures\Request as TestRequest;

require_once __DIR__ . '/Fixtures/Request.php';

class RequestCollectionFacadeTest extends AbstractTestCase
{
    /** @var ClientInterface|MockObject */
    private $client;

    /** @var Request */
    private $request;

    /** @var Response */
    private $response;

    /** @var RequestCollectionFacade */
    private $facade;

    public function setUp()
    {
        $this->client = $this->getSimpleMock('Guzzle\Http\ClientInterface');
        $this->facade = new RequestCollectionFacade($this->client);
        $this->request = new Request('GET', '/_request/latest');
        $this->request->setClient($this->client);

        $recordedRequest = new TestRequest();
        $recordedRequest->setMethod('POST');
        $recordedRequest->setRequestUri('/foo');
        $recordedRequest->setContent('RECOREDED=1');

        $this->response = new Response('200', ['Content-Type' => 'text/html'], (string) $recordedRequest);
    }

    public function testRequestingLatestRequest()
    {
        $this->client
            ->expects($this->once())
            ->method('get')
            ->with('/_request/latest')
            ->will($this->returnValue($this->request));

        $this->client
            ->expects($this->once())
            ->method('send')
            ->with($this->request)
            ->will($this->returnValue($this->response));

        $request = $this->facade->latest();

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/foo', $request->getPath());
        $this->assertSame('RECOREDED=1', (string) $request->getBody());
    }
}
