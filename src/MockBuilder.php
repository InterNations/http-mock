<?php
namespace InterNations\Component\HttpMock;

use Closure;
use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;

class MockBuilder
{
    private const PRIORITY_ANY = 0;
    private const PRIORITY_EXACTLY = 10;
    private const PRIORITY_NTH = 100;

    /** @var Expectation[] */
    private array $expectations = [];

    private MatcherFactory $matcherFactory;

    private Closure $limiter;

    private ExtractorFactory $extractorFactory;

    private int $priority;

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
        $this->priority = self::PRIORITY_EXACTLY;

        return $this;
    }

    public function first()
    {
        return $this->nth(1);
    }

    public function second()
    {
        return $this->nth(2);
    }

    public function third()
    {
        return $this->nth(3);
    }

    public function nth($position)
    {
        $this->limiter = static function ($runs) use ($position) {
            return $runs === ($position - 1);
        };
        $this->priority = $position * self::PRIORITY_NTH;

        return $this;
    }

    public function any()
    {
        $this->limiter = static function () {
            return true;
        };
        $this->priority = self::PRIORITY_ANY;

        return $this;
    }

    /***/
    public function when(): Expectation
    {
        $this->expectations[] = new Expectation(
            $this,
            $this->matcherFactory,
            $this->extractorFactory,
            $this->limiter,
            $this->priority
        );

        $this->any();

        return end($this->expectations);
    }

    public function flushExpectations()
    {
        $expectations = $this->expectations;
        $this->expectations = [];

        usort(
            $expectations,
            static function (Expectation $left, Expectation $right): int {
                return $left->getPriority() <=> $right->getPriority();
            }
        );

        return $expectations;
    }
}
