<?php
namespace InterNations\Component\HttpMock;

use InterNations\Component\HttpMock\Matcher\MatcherFactory;

class MockBuilder
{
    private $expectations = [];

    private $matcherFactory;

    private $limiter;

    public function __construct(MatcherFactory $matcherFactory)
    {
        $this->matcherFactory = $matcherFactory;
        $this->any();
    }

    public function once()
    {
        $this->exactly(1);
        return $this;
    }

    public function exactly($times)
    {
        $this->limiter = static function ($runs) use ($times) {
            return $runs < $times;
        };
        return $this;
    }

    public function any()
    {
        $this->limiter = static function () {
            return true;
        };
        return $this;
    }

    /**
     * @return Expectation
     */
    public function when()
    {
        $this->expectations[] = new Expectation($this, $this->matcherFactory, $this->limiter);

        $this->any();

        return end($this->expectations);
    }

    public function getExpectations()
    {
        return $this->expectations;
    }

    public function resetExpectations()
    {
        $this->expectations = [];
    }
}
