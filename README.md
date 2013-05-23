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
