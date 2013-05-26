<?php
namespace InterNations\Component\HttpMock;

use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Message\RequestInterface;
use Guzzle\Http\Message\Response;
use InterNations\Component\HttpMock\Request\UnifiedRequest;

class RequestCollectionFacade
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @return UnifiedRequest
     */
    public function latest()
    {
        return $this->getRecordedRequest('/_request/latest');
    }

    /**
     * @param Response $response
     * @return RequestInterface
     */
    private function parseRequestFromResponse(Response $response)
    {
        $requestFactory = RequestFactory::getInstance();

        return new UnifiedRequest($requestFactory->fromMessage($response->getBody()));
    }

    private function getRecordedRequest($path)
    {
        $response = $this->client
            ->get($path)
            ->send();

        return $this->parseRequestFromResponse($response);
    }
}
