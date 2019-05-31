## The server

Overview of the internal server functionality

### Setting up expectation for request recording
```
POST /_expectation
{
    response (required): stringified http respopnse
    responseCallback (optional): serialized closure that is passed in the response, and can return a new Response
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

### Count recorded requests
```
GET /_request/count
Content-Type: text/plain

REQUEST COUNT
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
