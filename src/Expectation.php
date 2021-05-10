<?php
namespace InterNations\Component\HttpMock;

use InterNations\Component\HttpMock\Matcher\ExtractorFactory;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\Matcher\Matcher;
use SuperClosure\SerializableClosure;
use Closure;
use Symfony\Component\HttpFoundation\Response;

class Expectation
{
    /** @var array<Matcher> */
    private array $matcher = [];
    private MatcherFactory $matcherFactory;
    private ResponseBuilder $responseBuilder;

    private Closure $limiter;

    private ExtractorFactory $extractorFactory;

    private int $priority;

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

    /** @param string|Matcher $matcher */
    public function pathIs($matcher): self
    {
        $this->appendMatcher($matcher, $this->extractorFactory->createPathExtractor());

        return $this;
    }

    /** @param string|Matcher $matcher */
    public function methodIs($matcher): self
    {
        $this->appendMatcher($matcher, $this->extractorFactory->createMethodExtractor());

        return $this;
    }

    /** @param string|Matcher $matcher */
    public function queryParamIs(string $param, $matcher): self
    {
        $this->appendMatcher($matcher, $this->extractorFactory->createParamExtractor($param));

        return $this;
    }

    public function queryParamExists(string $param): self
    {
        $this->appendMatcher(true, $this->extractorFactory->createParamExistsExtractor($param));

        return $this;
    }

    public function queryParamNotExists(string $param): self
    {
        $this->appendMatcher(false, $this->extractorFactory->createParamExistsExtractor($param));

        return $this;
    }

    /** @param array<string,Matcher|string> $paramMap */
    public function queryParamsAre(array $paramMap): self
    {
        foreach ($paramMap as $param => $value) {
            $this->queryParamIs($param, $value);
        }

        return $this;
    }

    /** @param array<string> $params */
    public function queryParamsExist(array $params): self
    {
        foreach ($params as $param) {
            $this->queryParamExists($param);
        }

        return $this;
    }

    /** @param array<string> $params */
    public function queryParamsNotExist(array $params): self
    {
        foreach ($params as $param) {
            $this->queryParamNotExists($param);
        }

        return $this;
    }

    /** @param string|Matcher $value */
    public function headerIs(string $name, $value): self
    {
        $this->appendMatcher($value, $this->extractorFactory->createHeaderExtractor($name));

        return $this;
    }

    public function headerExists(string $name): self
    {
        $this->appendMatcher(true, $this->extractorFactory->createHeaderExistsExtractor($name));

        return $this;
    }

    public function callback(Closure $callback): self
    {
        $this->appendMatcher($this->matcherFactory->closure($callback));

        return $this;
    }

    /** @return array<SerializableClosure>  */
    public function getMatcherClosures(): array
    {
        $closures = [];

        foreach ($this->matcher as $matcher) {
            $closures[] = $matcher->getMatcher();
        }

        return $closures;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function then(): ResponseBuilder
    {
        return $this->responseBuilder;
    }

    public function getResponse(): Response
    {
        return $this->responseBuilder->getResponse();
    }

    public function getLimiter(): SerializableClosure
    {
        return new SerializableClosure($this->limiter);
    }

    /** @param string|Matcher $matcher */
    private function appendMatcher($matcher, Closure $extractor = null): void
    {
        $matcher = $this->createMatcher($matcher);

        if ($extractor) {
            $matcher->setExtractor($extractor);
        }

        $this->matcher[] = $matcher;
    }

    /** @param string|Matcher $matcher */
    private function createMatcher($matcher): Matcher
    {
        return $matcher instanceof Matcher ? $matcher : $this->matcherFactory->str($matcher);
    }
}
