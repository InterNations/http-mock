<?php
namespace InterNations\Component\HttpMock\Tests;

use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\MockBuilder;
use InterNations\Component\HttpMock\Server;
use PHPUnit\Framework\TestCase;
use DateTime;
use DateTimeZone;
use InterNations\Component\HttpMock\Tests\Fixtures\Request as TestRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @large
 * @group integration
 */
class MockBuilderIntegrationTest extends TestCase
{
    private MockBuilder $builder;

    private MatcherFactory $matches;

    private Server $server;

    public function setUp(): void
    {
        $this->matches = new MatcherFactory();
        $this->builder = new MockBuilder($this->matches, new ExtractorFactory());
        $this->server = new Server(HTTP_MOCK_PORT, HTTP_MOCK_HOST);
        $this->server->start();
        $this->server->clean();
    }

    public function tearDown(): void
    {
        $this->server->stop();
    }

    public function testCreateExpectation(): void
    {
        $builder = $this->builder
            ->when()
                ->pathIs('/foo')
                ->methodIs($this->matches->regex('/POST/'))
                ->callback(static function (Request $request) {
                    error_log('CLOSURE MATCHER: ' . $request->getMethod() . ' ' . $request->getPathInfo());
                    return true;
                })
            ->then()
                ->statusCode(401)
                ->header('X-Foo', 'Bar')
                ->body('response body')
            ->end();

        self::assertSame($this->builder, $builder);

        $expectations = $this->builder->flushExpectations();

        self::assertCount(1, $expectations);
        /** @var Expectation $expectation */
        $expectation = current($expectations);

        $request = new TestRequest();
        $request->setMethod('POST');
        $request->setRequestUri('/foo');

        $run = 0;
        $oldValue = ini_set('error_log', '/dev/null');
        foreach ($expectation->getMatcherClosures() as $closure) {
            self::assertTrue($closure($request));

            $unserializedClosure = unserialize(serialize($closure));
            self::assertTrue($unserializedClosure($request));

            $run++;
        }
        ini_set('error_log', $oldValue);
        self::assertSame(3, $run);

        $expectation->getResponse()->setDate(new DateTime('2012-11-10 09:08:07', new DateTimeZone('UTC')));
        $response = "HTTP/1.0 401 Unauthorized\r\nCache-Control: no-cach%s\r\nDate:          Sat, 10 Nov 2012 09:08:07 GMT\r\nX-Foo:         Bar\r\n\r\nresponse body";
        self::assertStringMatchesFormat($response, (string)$expectation->getResponse());


        $this->server->setUp($expectations);

        $client = $this->server->getClient();

        self::assertSame('response body', (string) $client->post('/foo')->send()->getBody());

        self::assertNotFalse(strpos($this->server->getErrorOutput(), 'CLOSURE MATCHER: POST /foo'));
    }

    public function testCreateTwoExpectationsAfterEachOther(): void
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

        self::assertSame('POST 1', (string) $this->server->getClient()->post('/post-resource-1')->send()->getBody());
        self::assertSame('POST 2', (string) $this->server->getClient()->post('/post-resource-2')->send()->getBody());
        self::assertSame('POST 1', (string) $this->server->getClient()->post('/post-resource-1')->send()->getBody());
        self::assertSame('POST 2', (string) $this->server->getClient()->post('/post-resource-2')->send()->getBody());
    }

    public function testCreateSuccessiveExpectationsOnSameWhen(): void
    {
      $this->builder
          ->first()
          ->when()
              ->pathIs('/resource')
              ->methodIs('POST')
          ->then()
              ->body('called once');
      $this->builder
          ->second()
          ->when()
              ->pathIs('/resource')
              ->methodIs('POST')
          ->then()
              ->body('called twice');
      $this->builder
          ->nth(3)
          ->when()
              ->pathIs('/resource')
              ->methodIs('POST')
          ->then()
              ->body('called 3 times');

      $this->server->setUp($this->builder->flushExpectations());

      self::assertSame('called once', (string) $this->server->getClient()->post('/resource')->send()->getBody());
      self::assertSame('called twice', (string) $this->server->getClient()->post('/resource')->send()->getBody());
      self::assertSame('called 3 times', (string) $this->server->getClient()->post('/resource')->send()->getBody());
    }

    public function testCreateSuccessiveExpectationsWithAny(): void
    {
        $this->builder
            ->first()
            ->when()
                ->pathIs('/resource')
                ->methodIs('POST')
            ->then()
                ->body('1');
        $this->builder
            ->second()
            ->when()
                ->pathIs('/resource')
                ->methodIs('POST')
            ->then()
                ->body('2');
        $this->builder
            ->any()
                ->when()
                ->pathIs('/resource')
            ->methodIs('POST')
            ->then()
                ->body('any');

        $this->server->setUp($this->builder->flushExpectations());

        self::assertSame('1', (string) $this->server->getClient()->post('/resource')->send()->getBody());
        self::assertSame('2', (string) $this->server->getClient()->post('/resource')->send()->getBody());
        self::assertSame('any', (string) $this->server->getClient()->post('/resource')->send()->getBody());
    }

    public function testCreateSuccessiveExpectationsInUnexpectedOrder(): void
    {
        $this->builder
            ->second()
            ->when()
                ->pathIs('/resource')
                ->methodIs('POST')
            ->then()
                ->body('2');
        $this->builder
            ->first()
            ->when()
                ->pathIs('/resource')
                ->methodIs('POST')
            ->then()
                ->body('1');

        $this->server->setUp($this->builder->flushExpectations());

        self::assertSame('1', (string) $this->server->getClient()->post('/resource')->send()->getBody());
        self::assertSame('2', (string) $this->server->getClient()->post('/resource')->send()->getBody());
    }

    public function testCreateSuccessiveExpectationsWithOnce(): void
    {
        $this->builder
            ->first()
            ->when()
                ->pathIs('/resource')
                ->methodIs('POST')
            ->then()
                ->body('1');
        $this->builder
            ->second()
            ->when()
                ->pathIs('/resource')
                ->methodIs('POST')
            ->then()
                ->body('2');
        $this->builder
            ->twice()
            ->when()
                ->pathIs('/resource')
                ->methodIs('POST')
            ->then()
                ->body('twice');

        $this->server->setUp($this->builder->flushExpectations());

        self::assertSame('1', (string) $this->server->getClient()->post('/resource')->send()->getBody());
        self::assertSame('2', (string) $this->server->getClient()->post('/resource')->send()->getBody());
        self::assertSame('twice', (string) $this->server->getClient()->post('/resource')->send()->getBody());
        self::assertSame('twice', (string) $this->server->getClient()->post('/resource')->send()->getBody());
        self::assertSame('Expectation not met', (string) $this->server->getClient()->post('/resource')->send()->getBody());
    }
}
