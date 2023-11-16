<?php

namespace InterNations\Component\HttpMock;

use Closure;
use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;

class MockBuilder
{
    /** @var Expectation[] */
    private array $expectations = [];

    private MatcherFactory $matcherFactory;

    private Closure $limiter;

    private ExtractorFactory $extractorFactory;

    public function __construct(MatcherFactory $matcherFactory, ExtractorFactory $extractorFactory)
    {
        $this->matcherFactory = $matcherFactory;
        $this->extractorFactory = $extractorFactory;
        $this->any();
    }

    public function once() : MockBuilder
    {
        return $this->exactly(1);
    }

    public function twice() : MockBuilder
    {
        return $this->exactly(2);
    }

    public function thrice() : MockBuilder
    {
        return $this->exactly(3);
    }

    public function exactly($times) : MockBuilder
    {
        $this->limiter = static function ($runs) use ($times) {
            return $runs < $times;
        };

        return $this;
    }

    public function any() : MockBuilder
    {
        $this->limiter = static function () {
            return true;
        };

        return $this;
    }

    public function when() : Expectation
    {
        $this->expectations[] = new Expectation($this, $this->matcherFactory, $this->extractorFactory, $this->limiter);

        $this->any();

        return end($this->expectations);
    }

    /** @return Expectation[] */
    public function flushExpectations() : array
    {
        $expectations = $this->expectations;
        $this->expectations = [];

        return $expectations;
    }
}
