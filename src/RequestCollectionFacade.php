<?php

namespace InterNations\Component\HttpMock;

use Countable;
use GuzzleHttp\Client;
use function GuzzleHttp\Psr7\parse_request;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class RequestCollectionFacade implements Countable
{
    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function latest() : RequestInterface
    {
        return $this->getRecordedRequest('/_request/last');
    }

    public function last() : RequestInterface
    {
        return $this->getRecordedRequest('/_request/last');
    }

    public function first() : RequestInterface
    {
        return $this->getRecordedRequest('/_request/first');
    }

    public function at($position) : RequestInterface
    {
        return $this->getRecordedRequest('/_request/' . $position);
    }

    public function pop() : RequestInterface
    {
        return $this->deleteRecordedRequest('/_request/last');
    }

    public function shift() : RequestInterface
    {
        return $this->deleteRecordedRequest('/_request/first');
    }

    public function count()
    {
        $response = $this->client
            ->get('/_request/count');

        return (int) $response->getBody()->getContents();
    }

    private function parseRequestFromResponse(ResponseInterface $response, $path) : Request
    {
        try {
            $contents = $response->getBody()->getContents();
            $requestInfo = Util::deserialize($contents);
        } catch (UnexpectedValueException $e) {
            throw new UnexpectedValueException(sprintf('Cannot deserialize response from "%s": "%s"', $path, $contents), null, $e);
        }

        return parse_request($requestInfo['request']);
    }

    private function getRecordedRequest($path)
    {
        $response = $this->client
            ->get($path);

        return $this->parseResponse($response, $path);
    }

    private function deleteRecordedRequest($path)
    {
        $response = $this->client
            ->delete($path);

        return $this->parseResponse($response, $path);
    }

    private function parseResponse(ResponseInterface $response, $path)
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new UnexpectedValueException(sprintf('Expected status code 200 from "%s", got %d', $path, $statusCode));
        }

        $contentType = $response->hasHeader('content-type')
            ? $response->getHeaderLine('content-type')
            : '';

        if (substr($contentType, 0, 10) !== 'text/plain') {
            throw new UnexpectedValueException(sprintf('Expected content type "text/plain" from "%s", got "%s"', $path, $contentType));
        }

        return $this->parseRequestFromResponse($response, $path);
    }
}
