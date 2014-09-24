<?php
namespace InterNations\Component\HttpMock;

use Closure;
use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;

class MockBuilder
{
    /** @var Expectation[] */
    private $expectations = [];

    /** @var MatcherFactory */
    private $matcherFactory;

    /** @var Closure */
    private $limiter;
    /**
     * @var ExtractorFactory
     */
    private $extractorFactory;

    public function __construct(MatcherFactory $matcherFactory, ExtractorFactory $extractorFactory)
    {
        $this->matcherFactory = $matcherFactory;
        $this->extractorFactory = $extractorFactory;
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

    /** @return Expectation */
    public function when()
    {
        $this->expectations[] = new Expectation($this, $this->matcherFactory, $this->extractorFactory, $this->limiter);

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
