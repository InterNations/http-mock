<?php

namespace InterNations\Component\HttpMock\Tests;

use InterNations\Component\HttpMock\Expectation;
use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\MockBuilder;
use InterNations\Component\HttpMock\Server;
use InterNations\Component\HttpMock\Tests\Fixtures\Request as TestRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface as Request;

/**
 * @large
 * @group integration
 */
class MockBuilderIntegrationTest extends TestCase
{
    /** @var MockBuilder */
    private $builder;

    /** @var MatcherFactory */
    private $matches;

    /** @var Server */
    private $server;

    public function setUp()
    {
        $this->matches = new MatcherFactory();
        $this->builder = new MockBuilder($this->matches, new ExtractorFactory());
        $this->server = new Server(HTTP_MOCK_PORT, HTTP_MOCK_HOST);
        $this->server->start();
        $this->server->clean();
    }

    public function tearDown()
    {
        $this->server->stop();
    }

    public function testCreateExpectation()
    {
        $builder = $this->builder
            ->when()
                ->pathIs('/foo')
                ->methodIs($this->matches->regex('/POST/'))
                ->callback(static function (Request $request) {
                    error_log('CLOSURE MATCHER: ' . $request->getMethod() . ' ' . $request->getUri()->getPath());

                    return true;
                })
            ->then()
                ->statusCode(401)
                ->body('response body')
                ->header('X-Foo', 'Bar')
            ->end();

        $this->assertSame($this->builder, $builder);

        $expectations = $this->builder->flushExpectations();

        $this->assertCount(1, $expectations);
        /** @var Expectation $expectation */
        $expectation = current($expectations);

        $request = new TestRequest('POST', '/foo');

        $run = 0;
        $oldValue = ini_set('error_log', '/dev/null');
        foreach ($expectation->getMatcherClosures() as $closure) {
            $this->assertTrue($closure($request));

            $unserializedClosure = unserialize(serialize($closure));
            $this->assertTrue($unserializedClosure($request));

            ++$run;
        }
        ini_set('error_log', $oldValue);
        $this->assertSame(3, $run);

        $this->server->setUp($expectations);

        $client = $this->server->getClient();

        $this->assertSame('response body', (string) $client->post('/foo')->getBody());

        $this->assertContains('CLOSURE MATCHER: POST /foo', $this->server->getErrorOutput());
    }

    public function testCreateTwoExpectationsAfterEachOther()
    {
        $this->builder
            ->when()
                ->pathIs('/post-resource-1')
                ->methodIs('POST')
            ->then()
                ->statusCode(200)
                ->body('POST 1')
        ->end();
        $this->server->setUp($this->builder->flushExpectations());

        $this->builder
            ->when()
                ->pathIs('/post-resource-2')
                ->methodIs($this->matches->regex('/POST/'))
            ->then()
                ->statusCode(200)
                ->body('POST 2')
            ->end();
        $this->server->setUp($this->builder->flushExpectations());

        $this->assertSame('POST 1', (string) $this->server->getClient()->post('/post-resource-1')->getBody());
        $this->assertSame('POST 2', (string) $this->server->getClient()->post('/post-resource-2')->getBody());
        $this->assertSame('POST 1', (string) $this->server->getClient()->post('/post-resource-1')->getBody());
        $this->assertSame('POST 2', (string) $this->server->getClient()->post('/post-resource-2')->getBody());
    }
}
