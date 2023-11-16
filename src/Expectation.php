<?php

namespace InterNations\Component\HttpMock;

use Closure;
use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\Matcher\MatcherInterface;
use InterNations\Component\HttpMock\Matcher\RegexMatcher;
use Opis\Closure\SerializableClosure;
use Psr\Http\Message\ResponseInterface;

class Expectation
{
    /** @var MatcherInterface[] */
    private array $matcher = [];

    private MatcherFactory $matcherFactory;

    private ResponseBuilder $responseBuilder;

    private Closure $limiter;

    private ExtractorFactory $extractorFactory;

    public function __construct(
        MockBuilder $mockBuilder,
        MatcherFactory $matcherFactory,
        ExtractorFactory $extractorFactory,
        Closure $limiter
    ) {
        $this->matcherFactory = $matcherFactory;
        $this->responseBuilder = new ResponseBuilder($mockBuilder);
        $this->extractorFactory = $extractorFactory;
        $this->limiter = $limiter;
    }

    public function pathIs(string $matcher): Expectation
    {
        $this->appendMatcher($matcher, $this->extractorFactory->createPathExtractor());

        return $this;
    }

    public function methodIs(mixed $matcher): Expectation
    {
        $this->appendMatcher($matcher, $this->extractorFactory->createMethodExtractor());

        return $this;
    }

    public function queryParamIs(string $param, string $matcher): Expectation
    {
        $this->appendMatcher($matcher, $this->extractorFactory->createParamExtractor($param));

        return $this;
    }

    public function queryParamExists(string $param): Expectation
    {
        $this->appendMatcher(true, $this->extractorFactory->createParamExistsExtractor($param));

        return $this;
    }

    public function queryParamNotExists(string $param): Expectation
    {
        $this->appendMatcher(false, $this->extractorFactory->createParamExistsExtractor($param));

        return $this;
    }

    public function queryParamsAre(array $paramMap) : Expectation
    {
        foreach ($paramMap as $param => $value) {
            $this->queryParamIs($param, $value);
        }

        return $this;
    }

    public function queryParamsExist(array $params) : Expectation
    {
        foreach ($params as $param) {
            $this->queryParamExists($param);
        }

        return $this;
    }

    public function queryParamsNotExist(array $params) : Expectation
    {
        foreach ($params as $param) {
            $this->queryParamNotExists($param);
        }

        return $this;
    }

    public function headerIs(string $name, string $value) : Expectation
    {
        $this->appendMatcher($value, $this->extractorFactory->createHeaderExtractor($name));

        return $this;
    }

    public function headerExists(string $name) : Expectation
    {
        $this->appendMatcher(true, $this->extractorFactory->createHeaderExistsExtractor($name));

        return $this;
    }

    public function callback(Closure $callback) : Expectation
    {
        $this->appendMatcher($this->matcherFactory->closure($callback));

        return $this;
    }

    /** @return SerializableClosure[]  */
    public function getMatcherClosures() : array
    {
        $closures = [];

        foreach ($this->matcher as $matcher) {
            $closures[] = $matcher->getMatcher();
        }

        return $closures;
    }

    public function then() : ResponseBuilder
    {
        return $this->responseBuilder;
    }

    public function getResponse() : ResponseInterface
    {
        return $this->responseBuilder->getResponse();
    }

    public function getResponseCallback() : ?callable
    {
        return $this->responseBuilder->getResponseCallback();
    }

    public function getLimiter(): SerializableClosure
    {
        return new SerializableClosure($this->limiter);
    }

    private function appendMatcher(mixed $matcher, Closure $extractor = null): void
    {
        $matcher = $this->createMatcher($matcher);

        if ($extractor) {
            $matcher->setExtractor($extractor);
        }

        $this->matcher[] = $matcher;
    }

    private function createMatcher(mixed $matcher) : MatcherInterface
    {
        return $matcher instanceof MatcherInterface ? $matcher : $this->matcherFactory->str($matcher);
    }
}
