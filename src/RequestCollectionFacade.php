<?php
namespace InterNations\Component\HttpMock;

use Countable;
use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\EntityEnclosingRequestInterface;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use InterNations\Component\HttpMock\Request\UnifiedRequest;
use UnexpectedValueException;

class RequestCollectionFacade implements Countable
{
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     */
    public function latest(): UnifiedRequest
    {
        return $this->getRecordedRequest('/_request/last');
    }

    /**
     */
    public function last(): UnifiedRequest
    {
        return $this->getRecordedRequest('/_request/last');
    }

    /**
     */
    public function first(): UnifiedRequest
    {
        return $this->getRecordedRequest('/_request/first');
    }

    /**
     */
    public function at(int $position): UnifiedRequest
    {
       return $this->getRecordedRequest('/_request/' . $position);
    }

    /**
     */
    public function pop(): UnifiedRequest
    {
        return $this->deleteRecordedRequest('/_request/last');
    }

    /**
     */
    public function shift(): UnifiedRequest
    {
        return $this->deleteRecordedRequest('/_request/first');
    }

    public function count(): int
    {
        $response = $this->client
            ->get('/_request/count')
            ->send();

        return (int) $response->getBody(true);
    }

    /**
     * @throws UnexpectedValueException
     */
    private function parseRequestFromResponse(Response $response, string $path): UnifiedRequest
    {
        try {
            $requestInfo = Util::deserialize($response->getBody());
        } catch (UnexpectedValueException $e) {
            throw new UnexpectedValueException(
                sprintf('Cannot deserialize response from "%s": "%s"', $path, $response->getBody()),
                null,
                $e
            );
        }

        $request = RequestFactory::getInstance()->fromMessage($requestInfo['request']);
        $params = $this->configureRequest(
            $request,
            $requestInfo['server'],
            $requestInfo['enclosure'] ?? []
        );

        return new UnifiedRequest($request, $params);
    }

    /**
     * @param array<string,string> $server
     * @param array<string,string> $enclosure
     * @return array<string,string>
     */
    private function configureRequest(RequestInterface $request, array $server, array $enclosure): array
    {
        if (isset($server['HTTP_HOST'])) {
            $request->setHost($server['HTTP_HOST']);
        }

        if (isset($server['HTTP_PORT'])) {
            $request->setPort($server['HTTP_PORT']);
        }

        if (isset($server['PHP_AUTH_USER'])) {
            $request->setAuth($server['PHP_AUTH_USER'], $server['PHP_AUTH_PW'] ?? null);
        }

        $params = [];

        if (isset($server['HTTP_USER_AGENT'])) {
            $params['userAgent'] = $server['HTTP_USER_AGENT'];
        }

        if ($request instanceof EntityEnclosingRequestInterface) {
            $request->addPostFields($enclosure);
        }

        return $params;
    }

    private function getRecordedRequest(string $path): UnifiedRequest
    {
        $response = $this->client
            ->get($path)
            ->send();

        return $this->parseResponse($response, $path);
    }

    private function deleteRecordedRequest(string $path): UnifiedRequest
    {
        $response = $this->client
            ->delete($path)
            ->send();

        return $this->parseResponse($response, $path);
    }

    private function parseResponse(Response $response, string $path): UnifiedRequest
    {
        $statusCode = (int) $response->getStatusCode();

        if ($statusCode !== 200) {
            throw new UnexpectedValueException(
                sprintf('Expected status code 200 from "%s", got %d', $path, $statusCode)
            );
        }

        $contentType = $response->hasHeader('content-type')
            ? $response->getContentType()
            : '';

        if (strpos($contentType, 'text/plain') !== 0) {
            throw new UnexpectedValueException(
                sprintf('Expected content type "text/plain" from "%s", got "%s"', $path, $contentType)
            );
        }

        return $this->parseRequestFromResponse($response, $path);
    }
}
