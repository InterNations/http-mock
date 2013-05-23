# EOS Fake API [![Build Status](https://travis-ci.org/InterNations/eos-fake-api.png?branch=master)](https://travis-ci.org/InterNations/eos-fake-api)

*BIG FAT WARNING:* software like this is inherently insecure. Only use in trusted, controlled environments.

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
