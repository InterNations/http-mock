# Recording API

Once a SUT (system under test) has fired HTTP requests, we often want to validate that our assumption about the nature
of those requests are valid. For that purpose HTTP mock stores every request for later inspection. The recorded requests
are presented as an instance of `Slim\Http\Response`.

```php
$this->http->mock
    ->when()
        ->methodIs('POST')
        ->pathIs('/resource')
    ->then()
        ->body('body')
    ->end();
$this->http->setUp();

// Trigger the SUTs functionality that invokes an HTTP request
$this->sut->executeHttpRequest();

$request = $this->http->requests->latest();
$this->assertSame(
    'application/json',
    (string) $request->getHeader('Content-Type'),
    'Client should send application/json'
);
```

You can access the `first()`, `latest()` or `last()` request, access a specific request with `at(int $position)`,
`pop()` or `shift()` a request from the stack or simply `count($this->http->requests)` to get the number of requests.
