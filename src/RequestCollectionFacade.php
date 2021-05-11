<?php
namespace InterNations\Component\HttpMock;

use Countable;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;
use UnexpectedValueException;

class RequestCollectionFacade implements Countable
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
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
        $response = $this->client
            ->get('/_request/count');

        return (int) (string) $response->getBody();
    }

    /**
     * @throws UnexpectedValueException
     */
    private function parseRequestFromResponse(Response $response, string $path): Request
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
        $response = $this->client->get($path);

        return $this->parseResponse($response, $path);
    }

    private function deleteRecordedRequest(string $path): Request
    {
        $response = $this->client->delete($path);

        return $this->parseResponse($response, $path);
    }

    private function parseResponse(Response $response, string $path): Request
    {
        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new UnexpectedValueException(
                sprintf('Expected status code 200 from "%s", got %d', $path, $statusCode)
            );
        }

        $contentType = $response->hasHeader('content-type')
            ? $response->getHeaderLine('content-type')
            : '';

        if (strpos($contentType, 'text/plain') !== 0) {
            throw new UnexpectedValueException(
                sprintf('Expected content type "text/plain" from "%s", got "%s"', $path, $contentType)
            );
        }

        return $this->parseRequestFromResponse($response, $path);
    }
}
