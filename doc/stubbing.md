# Stubbing API

When we want to fake HTTP requests, one of the important part is to craft responses a client is supposed to handle. This
process is called stubbing, as Gerard Meszaros explains in
[xUnit patterns](http://xunitpatterns.com/Mocks,%20Fakes,%20Stubs%20and%20Dummies.html). Consequently,
`internations/http-mock` should have been called `internations/http-stub` but it’s too late for that now.

## Matching

What we want to do is tell the fake server: "once you get a request that matches the following criteria, send the
following response". Let’s looks at a simple example:

```php
$this->http->mock
    ->when()
        ->methodIs('GET')
        ->pathIs('/resource')
    ->then()
        ->body('response')
    ->end();
```

The example above say: when we see a `GET` request asking for `/resource` respond with `response`. So far so good.
What we see here is internally syntactic sugar for the following, more verbose, example using plain callbacks.

```php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$this->http->mock
    ->when()
        ->callback(
            static function (Request $request) {
                return $request->getMethod() === 'GET' && $request->getPathInfo() === '/resource';
            }
        )
    ->then()
        ->callback(
            static function (Response $response) {
                $response->setBody('response');
            }
        )
    ->end();
```

What we can see above is that we use standard Symfony HTTP foundation `Request` and `Response` objects. If you want to
learn more about it, look at
[Symfony’s documentation](https://symfony.com/doc/current/components/http_foundation/introduction.html).

Let’s have a look what we can do with matching and response building shortcuts:

```php
use Symfony\Component\HttpFoundation\Response;

$this->http->mock
    ->when()
        ->methodIs('GET')
        ->pathIs('/resource')
    ->then()
        ->statusCode(Response::HTTP_NOT_FOUND)
        ->header('X-Custom-Header', 'Header Value')
        ->body('response')
    ->end();`
```

Additional matching methods are `queryParamExists(string $param)`, `queryParamNotExists(string $param)`,
`queryParamIs(string $param, mixed $value)`, `queryParamsExist(array $params)`, `queryParamsNotExist(array $params)`
 and `queryParamsAre(array $paramMap)`.

If you have more ideas for syntactic sugar, feel free to open a pull requests.

## Pattern matching

For more advanced matching, we can use the matcher API to match against regular expressions and even callbacks.

```php
$this->http->mock
    ->when()
        ->methodIs($this->http->matches->regex('/(GET|POST)/'))
        ->pathIs(
            $this->http->matches->callback(
                function ($path) {
                    return substr($path, -1) === '/';
                }
            )
        )
    ->then()
        ->body('response')
    ->end();`
```

## Limiting

If we need to simulate different responses for the same request based on the position, we can limit the scope of a single response
a single stub.

```php
$this->http->mock
    ->once()
    ->when()
        ->methodIs('GET')
        ->pathIs('/resource')
    ->then()
        ->body('response')
    ->end();
```

By using `once()`, the second request will lead to a 404 HTTP Not Found, as if the request would have been undefined.
Other methods to limit validity are `once()`, `twice()`, `thrice()` and `exactly(int $count)`.

## Getting different response on successive identical queries

In the previous section we saw how we could make a stub at most for N queries. But it's also possible to set up different responses on
successive identical queries.

```php
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
          ->nth(2) // "2" because the count starts at 0
          ->when()
              ->pathIs('/resource')
              ->methodIs('POST')
          ->then()
              ->body('called 3 times');
```
