<?php
namespace InterNations\Component\HttpMock\Tests;

use Closure;
use Guzzle\Http\Message\RequestInterface;
use InterNations\Component\HttpMock\Server;
use InterNations\Component\HttpMock\Util;
use InterNations\Component\Testing\AbstractTestCase;
use Guzzle\Http\Client;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Message\Response as GuzzleResponse;
use Opis\Closure\SerializableClosure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @large
 * @group integration
 */
class AppIntegrationTest extends AbstractTestCase
{
    private static Server $server1;

    private Client $client;

    public static function setUpBeforeClass(): void
    {
        static::$server1 = new Server(HTTP_MOCK_PORT, HTTP_MOCK_HOST);
        static::$server1->start();
    }

    public static function tearDownAfterClass(): void
    {
        static::assertSame('', (string) static::$server1->getOutput(), (string) static::$server1->getOutput());
        static::assertSame('', (string) static::$server1->getErrorOutput(), (string) static::$server1->getErrorOutput());
        static::$server1->stop();
    }

    public function setUp(): void
    {
        static::$server1->clean();
        $this->client = static::$server1->getClient();
    }

    public function testSimpleUseCase(): void
    {
        $response = $this->client->post(
            '/_expectation',
            null,
            $this->createExpectationParams(
                [
                    static function ($request) {
                        return $request instanceof Request;
                    },
                ],
                new Response('fake body', 200)
            )
        )->send();
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame(201, $response->getStatusCode());

        $response = $this->client->post('/foobar', ['X-Special' => 1], ['post' => 'data'])->send();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('fake body', (string) $response->getBody());

        $response = $this->client->get('/_request/latest')->send();

        /** @var Request $request */
        $request = $this->parseRequestFromResponse($response);
        $this->assertSame('1', (string) $request->headers->get('X-Special'));
        $this->assertSame('post=data', $request->getContent());
    }

    public function testRecording(): void
    {
        $this->client->delete('/_all')->send();

        $this->assertSame(404, $this->client->get('/_request/latest')->send()->getStatusCode());
        $this->assertSame(404, $this->client->get('/_request/0')->send()->getStatusCode());
        $this->assertSame(404, $this->client->get('/_request/first')->send()->getStatusCode());
        $this->assertSame(404, $this->client->get('/_request/last')->send()->getStatusCode());

        $this->client->get('/req/0')->send();
        $this->client->get('/req/1')->send();
        $this->client->get('/req/2')->send();
        $this->client->get('/req/3')->send();

        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/last')->send())->getRequestUri()
        );
        $this->assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->get('/_request/0')->send())->getRequestUri()
        );
        $this->assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->get('/_request/1')->send())->getRequestUri()
        );
        $this->assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->get('/_request/2')->send())->getRequestUri()
        );
        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->get('/_request/3')->send())->getRequestUri()
        );
        $this->assertSame(404, $this->client->get('/_request/4')->send()->getStatusCode());

        $this->assertSame(
            '/req/3',
            $this->parseRequestFromResponse($this->client->delete('/_request/last')->send())->getRequestUri()
        );
        $this->assertSame(
            '/req/0',
            $this->parseRequestFromResponse($this->client->delete('/_request/first')->send())->getRequestUri()
        );
        $this->assertSame(
            '/req/1',
            $this->parseRequestFromResponse($this->client->get('/_request/0')->send())->getRequestUri()
        );
        $this->assertSame(
            '/req/2',
            $this->parseRequestFromResponse($this->client->get('/_request/1')->send())->getRequestUri()
        );
        $this->assertSame(404, $this->client->get('/_request/2')->send()->getStatusCode());
    }

    public function testErrorHandling(): void
    {
        $this->client->delete('/_all')->send();

        $response = $this->client->post('/_expectation', null, ['matcher' => ''])->send();
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "matcher" must be a serialized list of closures', (string) $response->getBody());

        $response = $this->client->post('/_expectation', null, ['matcher' => ['foo']])->send();
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "matcher" must be a serialized list of closures', (string) $response->getBody());

        $response = $this->client->post('/_expectation', null, [])->send();
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "response" not found in POST data', (string) $response->getBody());

        $response = $this->client->post('/_expectation', null, ['response' => ''])->send();
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "response" must be a serialized Symfony response', (string) $response->getBody());

        $response = $this->client->post('/_expectation', null, ['response' => serialize(new Response()), 'limiter' => 'foo'])->send();
        $this->assertSame(417, $response->getStatusCode());
        $this->assertSame('POST data key "limiter" must be a serialized closure', (string) $response->getBody());
    }

    public function testNewestExpectationsAreFirstEvaluated(): void
    {
        $this->client->post(
            '/_expectation',
            null,
            $this->createExpectationParams(
                [
                    static function ($request) {
                        return $request instanceof Request;
                    },
                ],
                new Response('first', 200)
            )
        )->send();
        $this->assertSame('first', $this->client->get('/')->send()->getBody(true));

        $this->client->post(
            '/_expectation',
            null,
            $this->createExpectationParams(
                [
                    static function ($request) {
                        return $request instanceof Request;
                    },
                ],
                new Response('second', 200)
            )
        )->send();
        $this->assertSame('second', $this->client->get('/')->send()->getBody(true));
    }

    public function testServerLogsAreNotInErrorOutput(): void
    {
        $this->client->delete('/_all');

        $expectedServerErrorOutput = '[404]: (null) / - No such file or directory';

        self::$server1->addErrorOutput('PHP 7.4.2 Development Server (http://localhost:8086) started' . PHP_EOL);
        self::$server1->addErrorOutput('Accepted' . PHP_EOL);
        self::$server1->addErrorOutput($expectedServerErrorOutput . PHP_EOL);
        self::$server1->addErrorOutput('Closing' . PHP_EOL);

        $actualServerErrorOutput = self::$server1->getErrorOutput();

        $this->assertEquals($expectedServerErrorOutput, $actualServerErrorOutput);

        self::$server1->clearErrorOutput();
    }

    private function parseRequestFromResponse(GuzzleResponse $response): Request
    {
        return Util::deserialize($response->getBody());
    }

    /**
     * @param list<Closure> $closures
     * @return array{matcher: string, response: string}
     */
    private function createExpectationParams(array $closures, Response $response): array
    {
        foreach ($closures as $index => $closure) {
            $closures[$index] = new SerializableClosure($closure);
        }

        return [
            'matcher' => serialize($closures),
            'response' => serialize($response),
        ];
    }
}
