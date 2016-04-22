<?php
namespace InterNations\Component\HttpMock;

use Closure;
use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\Matcher\MatcherInterface;
use SuperClosure\SerializableClosure;
use Symfony\Component\HttpFoundation\Request;

class Expectation
{
    /** @var MatcherInterface[] */
    private $matcher = [];

    /** @var MockBuilder */
    private $mockBuilder;

    /** @var MatcherFactory */
    private $matcherFactory;

    /** @var ResponseBuilder */
    private $responseBuilder;

    /** @var Closure */
    private $limiter;

    /** @var ExtractorFactory */
    private $extractorFactory;

    public function __construct(
        MockBuilder $mockBuilder,
        MatcherFactory $matcherFactory,
        ExtractorFactory $extractorFactory,
        Closure $limiter
    )
    {
        $this->mockBuilder = $mockBuilder;
        $this->matcherFactory = $matcherFactory;
        $this->responseBuilder = new ResponseBuilder($this->mockBuilder);
        $this->extractorFactory = $extractorFactory;
        $this->limiter = $limiter;
    }

    public function pathIs($matcher)
    {
        $this->appendMatcher($matcher, $this->extractorFactory->createPathExtractor());

        return $this;
    }

    public function methodIs($matcher)
    {
        $this->appendMatcher($matcher, $this->extractorFactory->createMethodExtractor());

        return $this;
    }

    public function headerExists($name)
    {
        $this->appendMatcher(
            $this->matcherFactory->closure(function (Request $request) use ($name) {
                return $request->headers->has($name);
            })
        );

        return $this;
    }

    public function headerIs($name, $value)
    {
        $this->appendMatcher(
            $this->matcherFactory->closure(function (Request $request) use ($name, $value) {
                return $request->headers->contains($name, $value);
            })
        );

        return $this;
    }

    public function callback(Closure $callback)
    {
        $this->appendMatcher($this->matcherFactory->closure($callback));

        return $this;
    }

    /** @return SerializableClosure[]  */
    public function getMatcherClosures()
    {
        $closures = [];

        foreach ($this->matcher as $matcher) {
            $closures[] = $matcher->getMatcher();
        }

        return $closures;
    }

    public function then()
    {
        return $this->responseBuilder;
    }

    public function getResponse()
    {
        return $this->responseBuilder->getResponse();
    }

    public function getLimiter()
    {
        return new SerializableClosure($this->limiter);
    }

    private function appendMatcher($matcher, Closure $extractor = null)
    {
        $matcher = $this->createMatcher($matcher);

        if ($extractor) {
            $matcher->setExtractor($extractor);
        }

        $this->matcher[] = $matcher;
    }

    private function createMatcher($matcher)
    {
        return $matcher instanceof MatcherInterface ? $matcher : $this->matcherFactory->str($matcher);
    }
}
