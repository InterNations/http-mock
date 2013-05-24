# HTTP Mock for PHP [![Build Status](https://travis-ci.org/InterNations/eos-fake-api.png?branch=master)](https://travis-ci.org/InterNations/http-mock)

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

    public function testSimpleRequest($path)
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
}
```

## The server

### Setting up expectation for request recording
```
POST /_expectation
{
    response (required): serialized Symfony response
    matcher (optional): serialized list of closures
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
