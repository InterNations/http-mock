# Getting started with HTTP mock

HTTP mock comes out of the box with an integration with [PHPUnit](https://phpunit.de) in the shape of
`InterNations\Component\HttpMock\PHPUnit\HttpMockTrait`. In order to use it, we start and stop the background HTTP
server in `setUpBeforeClass()` and `tearDownAfterClass()` respectively.

```php
namespace Acme\Tests;

use PHPUnit\Framework\TestCase;
use InterNations\Component\HttpMock\PHPUnit\HttpMock;

class ExampleTest extends TestCase
{
    use HttpMock;

    public static function setUpBeforeClass(): void
    {
        static::setUpHttpMockBeforeClass('8082', 'localhost');
    }

    public static function tearDownAfterClass(): void
    {
        static::tearDownHttpMockAfterClass();
    }

    protected function setUp(): void
    {
        $this->setUpHttpMock();
    }

    protected function tearDown(): void
    {
        $this->tearDownHttpMock();
    }

    public function testSimpleRequest(): void
    {
        $this->http->mock
            ->when()
                ->methodIs('GET')
                ->pathIs('/foo')
            ->then()
                ->body('mocked body')
            ->end();
        $this->http->setUp();

        $this->assertSame('mocked body', file_get_contents('http://localhost:8082/foo'));
    }

    public function testAccessingRecordedRequests(): void
    {
        $this->http->mock
            ->when()
                ->methodIs('POST')
                ->pathIs('/foo')
            ->then()
                ->body('mocked body')
            ->end();
        $this->http->setUp();

        $this->assertSame('mocked body', $this->http->client->post('http://localhost:8082/foo')->send()->getBody(true));

        $this->assertSame('POST', $this->http->requests->latest()->getMethod());
        $this->assertSame('/foo', $this->http->requests->latest()->getPath());
    }
}
 ```
