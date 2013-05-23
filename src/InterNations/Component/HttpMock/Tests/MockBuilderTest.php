<?php
namespace InterNations\Component\HttpMock\Tests;

use Guzzle\Common\Event;
use Guzzle\Http\Client;
use Guzzle\Http\Message\Request;
use InterNations\Component\HttpMock\Expectation;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\MockBuilder;
use InterNations\Component\HttpMock\Server;
use PHPUnit_Framework_TestCase as TestCase;
use DateTime;

class MockBuilderTest extends TestCase
{
    /** @var MockBuilder */
    private $builder;

    /** @var MatcherFactory */
    private $matches;

    public function setUp()
    {
        $this->matches = new MatcherFactory();
        $this->builder = new MockBuilder($this->matches);
    }

    public function testCreateExpectation()
    {
        $builder = $this->builder
            ->when()
                ->pathIs('/foo')
                ->methodIs($this->matches->regex('/POST/'))
                ->callback(function ($request) {
                    error_log('CLOSURE MATCHER: ' . $request->getMethod() . ' ' . $request->getPath());
                    return true;
                })
            ->then()
                ->statusCode(401)
                ->body('response body')
                ->header('X-Foo', 'Bar')
            ->end();

        $this->assertSame($this->builder, $builder);

        $expectations = $this->builder->getExpectations();

        $this->assertCount(1, $expectations);
        /** @var Expectation $expectation */
        $expectation = current($expectations);

        $request = new Request('POST', '/foo');

        $run = false;
        foreach ($expectation->getMatcherClosures() as $pos => $closure) {
            $this->assertTrue($closure($request));

            $unserializedClosure = unserialize(serialize($closure));
            $this->assertTrue($unserializedClosure($request));

            $run = true;
        }
        $this->assertTrue($run);

        $expectation->getResponse()->setDate(new DateTime('2012-11-10 09:08:07'));
        $response = "HTTP/1.0 401 Unauthorized\r\nCache-Control: no-cache\r\nDate:          Sat, 10 Nov 2012 08:08:07 GMT\r\nX-Foo:         Bar\r\n\r\nresponse body";
        $this->assertSame($response, (string)$expectation->getResponse());

        $server = new Server();
        $this->builder->setUp($server);

        $client = new Client('http://localhost:28080');
        $client->getEventDispatcher()->addListener(
            'request.error',
            function(Event $event) {
                $event->stopPropagation();
            }
        );
        $this->assertSame('response body', (string) $client->post('/foo')->send()->getBody());

        $this->assertContains('CLOSURE MATCHER: POST /foo', $server->getErrorOutput());
    }
}
