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

    /** @var array<Expectation> */
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

    public function once(): self
    {
        return $this->exactly(1);
    }

    public function twice(): self
    {
        return $this->exactly(2);
    }

    public function thrice(): self
    {
        return $this->exactly(3);
    }

    public function exactly(int $times): self
    {
        $this->limiter = static function ($runs) use ($times) {
            return $runs < $times;
        };
        $this->priority = self::PRIORITY_EXACTLY;

        return $this;
    }

    public function first(): self
    {
        return $this->nth(1);
    }

    public function second(): self
    {
        return $this->nth(2);
    }

    public function third(): self
    {
        return $this->nth(3);
    }

    public function nth(int $position): self
    {
        $this->limiter = static function ($runs) use ($position) {
            return $runs === $position - 1;
        };
        $this->priority = $position * self::PRIORITY_NTH;

        return $this;
    }

    public function any(): self
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

    /** @return list<Expectation> */
    public function flushExpectations(): array
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
