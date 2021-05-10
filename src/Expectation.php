<?php
namespace InterNations\Component\HttpMock;

use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\Matcher\MatcherInterface;
use SuperClosure\SerializableClosure;
use Closure;

class Expectation
{
    /** @var MatcherInterface[] */
    private $matcher = [];

    /** @var MatcherFactory */
    private $matcherFactory;

    /** @var ResponseBuilder */
    private $responseBuilder;

    /** @var Closure */
    private $limiter;

    /** @var ExtractorFactory */
    private $extractorFactory;

    /** @var int */
    private $priority;

    public function __construct(
        MockBuilder $mockBuilder,
        MatcherFactory $matcherFactory,
        ExtractorFactory $extractorFactory,
        Closure $limiter,
        int $priority
    )
    {
        $this->matcherFactory = $matcherFactory;
        $this->responseBuilder = new ResponseBuilder($mockBuilder);
        $this->extractorFactory = $extractorFactory;
        $this->limiter = $limiter;
        $this->priority = $priority;
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

    public function queryParamIs($param, $matcher)
    {
        $this->appendMatcher($matcher, $this->extractorFactory->createParamExtractor($param));

        return $this;
    }

    public function queryParamExists($param)
    {
        $this->appendMatcher(true, $this->extractorFactory->createParamExistsExtractor($param));

        return $this;
    }

    public function queryParamNotExists($param)
    {
        $this->appendMatcher(false, $this->extractorFactory->createParamExistsExtractor($param));

        return $this;
    }

    public function queryParamsAre(array $paramMap)
    {
        foreach ($paramMap as $param => $value) {
            $this->queryParamIs($param, $value);
        }

        return $this;
    }

    public function queryParamsExist(array $params)
    {
        foreach ($params as $param) {
            $this->queryParamExists($param);
        }

        return $this;
    }

    public function queryParamsNotExist(array $params)
    {
        foreach ($params as $param) {
            $this->queryParamNotExists($param);
        }

        return $this;
    }

    public function headerIs($name, $value)
    {
        $this->appendMatcher($value, $this->extractorFactory->createHeaderExtractor($name));

        return $this;
    }

    public function headerExists($name)
    {
        $this->appendMatcher(true, $this->extractorFactory->createHeaderExistsExtractor($name));

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

    public function getPriority()
    {
        return $this->priority;
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
