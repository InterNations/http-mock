# EOS Fake API

### Setting up expectation for request recording
```
POST /_expectation
{
    OPTIONAL: body=string
    OPTIONAL: sleepMs=integer
    OPTIONAL: statusCode=integer
    OPTIONAL: callbackUrlPropertyPath=string
}
```

### Accessing recorded request
```
GET /_request/latest
Content-Type: text/plain

RECORDED REQUEST
```
