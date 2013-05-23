<?php
namespace InterNations\Component\HttpMock;

use Guzzle\Http\Client;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;

class MockBuilder
{
    private $expectations = [];

    private $matcherFactory;

    public function __construct(MatcherFactory $matcherFactory)
    {
        $this->matcherFactory = $matcherFactory;
    }

    /**
     * @return Expectation
     */
    public function when()
    {
        $this->expectations[] = new Expectation($this, $this->matcherFactory);

        return end($this->expectations);
    }

    public function getExpectations()
    {
        return $this->expectations;
    }

    public function setUp(Server $server)
    {
        $server->start();


        $client = new Client('http://localhost:28080');
        $client->delete('/_expectation')->send();

        /** @var Expectation $expectation */
        foreach ($this->expectations as $expectation) {
            $client->post(
                '/_expectation',
                null,
                [
                    'matchers' => serialize($expectation->getMatcherClosures()),
                    'response' => serialize($expectation->getResponse())
                ]
            )->send();
        }
    }
}
