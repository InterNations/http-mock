# HTTP Mock for PHP

Mock HTTP requests on the server side in your PHP unit tests.

HTTP Mock for PHP mocks the server side of an HTTP request to allow integration testing with the HTTP side.
It uses PHP’s builtin web server to start a second process that handles the mocking. The server allows
registering request matcher and responses from the client side.

*BIG FAT WARNING:* software like this is inherently insecure. Only use in trusted, controlled environments.

Not this is a fork of https://github.com/internations/http-mock

Its been updated to use PSR/7 Http methods, and Slim on the server side.
The API has been kept the same where possible, but any direct use of Request or Response objects
will be different.

## Usage

`composer require pagely/http-mock`

Read the [docs](doc/index.md)
