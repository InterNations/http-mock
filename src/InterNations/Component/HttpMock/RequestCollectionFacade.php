<?php
namespace InterNations\Component\HttpMock;

use Guzzle\Http\ClientInterface;
use Guzzle\Http\Message\EntityEnclosingRequest;
use Guzzle\Http\Message\Request;
use Guzzle\Http\Message\RequestFactory;
use Guzzle\Http\Message\Response;

class RequestCollectionFacade
{
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @return EntityEnclosingRequest
     */
    public function latest()
    {
        return $this->getRecordedRequest('/_request/latest');
    }

    /**
     * @param Response $response
     * @return bool|\Guzzle\Http\Message\RequestInterface
     */
    private function parseRequestFromResponse(Response $response)
    {
        $requestFactory = RequestFactory::getInstance();

        return $requestFactory->fromMessage($response->getBody());
    }

    private function getRecordedRequest($path)
    {
        $response = $this->client
            ->get($path)
            ->send();

        return $this->parseRequestFromResponse($response);
    }
}
