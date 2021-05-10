# HTTP Mock for PHP

[![Test](https://github.com/InterNations/http-mock/actions/workflows/test.yaml/badge.svg)](https://github.com/InterNations/http-mock/actions/workflows/test.yaml)

Mock HTTP requests on the server side in your PHP unit tests.

HTTP Mock for PHP mocks the server side of an HTTP request to allow integration testing with the HTTP side.
It uses PHP’s builtin web server to start a second process that handles the mocking. The server allows
registering request matcher and responses from the client side.

*BIG FAT WARNING:* software like this is inherently insecure. Only use in trusted, controlled environments.

## Usage

Read the [docs](doc/index.md)
