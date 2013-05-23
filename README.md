h1. EOS Fake API

h3. Setting up expectation for request recording

```
POST /_expectation
{
    OPTIONAL: body=string
    OPTIONAL: sleepMs=integer
    OPTIONAL: statusCode=integer
    OPTIONAL: callbackUrlPropertyPath=string
}
```

h3. Accessing recorded request
```
GET /_request/latest
Content-Type: text/plain

RECORDED REQUEST
```
