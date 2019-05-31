<?php
namespace Pagely\Component\HttpMock;

use Closure;
use Pagely\Component\HttpMock\Matcher\ExtractorFactory;
use Pagely\Component\HttpMock\Matcher\MatcherFactory;

class MockBuilder
{
    /** @var Expectation[] */
    private $expectations = [];

    /** @var MatcherFactory */
    private $matcherFactory;

    /** @var Closure */
    private $limiter;

    /** @var ExtractorFactory */
    private $extractorFactory;

    public function __construct(MatcherFactory $matcherFactory, ExtractorFactory $extractorFactory)
    {
        $this->matcherFactory = $matcherFactory;
        $this->extractorFactory = $extractorFactory;
        $this->any();
    }

    public function once()
    {
        return $this->exactly(1);
    }

    public function twice()
    {
        return $this->exactly(2);
    }

    public function thrice()
    {
        return $this->exactly(3);
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

    /** @return Expectation */
    public function when()
    {
        $this->expectations[] = new Expectation($this, $this->matcherFactory, $this->extractorFactory, $this->limiter);

        $this->any();

        return end($this->expectations);
    }

    public function flushExpectations()
    {
        $expectations = $this->expectations;
        $this->expectations = [];

        return $expectations;
    }
}
