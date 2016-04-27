# Getting started with HTTP mock

HTTP mock comes out of the box with an integration with (PHPUnit)[https://phpunit.de] in the shape of
`InterNations\Component\HttpMock\PHPUnit\HttpMockTrait`. In order to use it, we start and stop the background HTTP
server in `setUpBeforeClass()` and `tearDownAfterClass()` respectively.

```php
namespace Acme\Tests;

use InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;

class ExampleTest extends PHPUnit_Framework_TestCase
{
    use HttpMockTrait;

    public static function setUpBeforeClass()
    {
        static::setUpHttpMockBeforeClass('8082', 'localhost');
    }

    public static function tearDownAfterClass()
    {
        static::tearDownHttpMockAfterClass();
    }

    public function setUp()
    {
        $this->setUpHttpMock();
    }

    public function tearDown()
    {
        $this->tearDownHttpMock();
    }

    public function testSimpleRequest()
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

    public function testAccessingRecordedRequests()
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
