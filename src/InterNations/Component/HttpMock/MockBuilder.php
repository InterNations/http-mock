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
}
