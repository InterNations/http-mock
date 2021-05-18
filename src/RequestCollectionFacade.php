<?php
namespace InterNations\Component\HttpMock;

use Countable;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use UnexpectedValueException;

class RequestCollectionFacade implements Countable
{
    private ClientInterface $client;
    private RequestFactoryInterface $requestFactory;

    public function __construct(ClientInterface $client, RequestFactoryInterface $requestFactory)
    {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
    }

    public function latest(): Request
    {
        return $this->getRecordedRequest('/_request/last');
    }

    public function last(): Request
    {
        return $this->getRecordedRequest('/_request/last');
    }

    public function first(): Request
    {
        return $this->getRecordedRequest('/_request/first');
    }

    public function at(int $position): Request
    {
       return $this->getRecordedRequest('/_request/' . $position);
    }

    public function pop(): Request
    {
        return $this->deleteRecordedRequest('/_request/last');
    }

    public function shift(): Request
    {
        return $this->deleteRecordedRequest('/_request/first');
    }

    public function count(): int
    {
        return (int) (string) $this->client->sendRequest(
            $this->requestFactory->createRequest('GET', '/_request/count')
        )->getBody();
    }

    /**
     * @throws UnexpectedValueException
     */
    private function parseRequestFromResponse(ResponseInterface $response, string $path): Request
    {
        try {
            return Util::deserialize($response->getBody());
        } catch (UnexpectedValueException $e) {
            throw new UnexpectedValueException(
                sprintf('Cannot deserialize response from "%s": "%s"', $path, $response->getBody()),
                null,
                $e
            );
        }
    }

    private function getRecordedRequest(string $path): Request
    {
        return $this->parseResponse(
            $this->client->sendRequest($this->requestFactory->createRequest('GET', $path)),
            $path
        );
    }

    private function deleteRecordedRequest(string $path): Request
    {
        return $this->parseResponse(
            $this->client->sendRequest($this->requestFactory->createRequest('DELETE', $path)),
            $path
        );
    }

    private function parseResponse(ResponseInterface $response, string $path): Request
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new UnexpectedValueException(
                sprintf('Expected status code 200 from "%s", got %d', $path, $statusCode)
            );
        }

        $contentType = $response->getHeaderLine('content-type');

        if (strpos($contentType, 'text/plain') !== 0) {
            throw new UnexpectedValueException(
                sprintf('Expected content type "text/plain" from "%s", got "%s"', $path, $contentType)
            );
        }

        return $this->parseRequestFromResponse($response, $path);
    }
}
