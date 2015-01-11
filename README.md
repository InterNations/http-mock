# HTTP Mock for PHP

[![Gitter](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/InterNations/http-mock?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge) [![Build Status](https://travis-ci.org/InterNations/http-mock.svg)](https://travis-ci.org/InterNations/http-mock) [![Dependency Status](https://www.versioneye.com/user/projects/53479c42fe0d0720b500006a/badge.png)](https://www.versioneye.com/user/projects/53479c42fe0d0720b500006a) [![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/InterNations/http-mock.svg)](http://isitmaintained.com/project/InterNations/http-mock "Average time to resolve an issue") [![Percentage of issues still open](http://isitmaintained.com/badge/open/InterNations/http-mock.svg)](http://isitmaintained.com/project/InterNations/http-mock "Percentage of issues still open")

Mock HTTP requests on the server side in your PHP unit tests.

HTTP Mock for PHP mocks the server side of an HTTP request to allow integration testing with the HTTP side.
It uses PHP’s builtin web server to start a second process that handles the mocking. The server allows
registering request matcher and responses from the client side.

*BIG FAT WARNING:* software like this is inherently insecure. Only use in trusted, controlled environments.

## The client side API

### Using HTTP mock with PHPUnit
```php
class ExampleTest extends PHPUnit_Framework_TestCase
{
    use \InterNations\Component\HttpMock\PHPUnit\HttpMockTrait;

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

## The server

### Setting up expectation for request recording
```
POST /_expectation
{
    response (required): serialized Symfony response
    matcher (optional): serialized list of closures
    limiter (optional): serialized closure that limits the validity of the expectation
}
```

### Accessing latest recorded request
```
GET /_request/latest
Content-Type: text/plain

RECORDED REQUEST
```

### Accessing recorded request by index
```
GET /_request/{{index}}
Content-Type: text/plain

RECORDED REQUEST
```

### Shift recorded request
```
GET /_request/shift
Content-Type: text/plain

RECORDED REQUEST
```

### Pop recorded request
```
GET /_request/pop
Content-Type: text/plain

RECORDED REQUEST
```

### Deleting expectations
```
DELETE /_expectation
```

### Deleting recorded requests
```
DELETE /_request
```

### Deleting everything
```
DELETE /_all
```

### Introspection
```
GET /_me
```
