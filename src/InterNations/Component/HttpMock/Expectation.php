<?php
namespace InterNations\Component\HttpMock;

use InterNations\Component\HttpMock\Builder\MockBuilder;
use InterNations\Component\HttpMock\Builder\ResponseBuilder;
use Symfony\Component\HttpFoundation\Request;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\Matcher\MatcherInterface;
use Jeremeamia\SuperClosure\SerializableClosure;
use Closure;

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

    public function __construct(MockBuilder $mockBuilder, MatcherFactory $matcherFactory, Closure $limiter)
    {
        $this->mockBuilder = $mockBuilder;
        $this->matcherFactory = $matcherFactory;
        $this->responseBuilder = new ResponseBuilder($this->mockBuilder);
        $this->limiter = $limiter;
    }

    public function pathIs($matcher)
    {
        if (!$matcher instanceof MatcherInterface) {
            $matcher = $this->matcherFactory->str($matcher);
        }
        $matcher->setExtractor(
            static function (Request $request) {
                return $request->getRequestUri();
            }
        );
        $this->matcher[] = $matcher;

        return $this;
    }

    public function methodIs($matcher)
    {
        if (!$matcher instanceof MatcherInterface) {
            $matcher = $this->matcherFactory->str($matcher);
        }
        $matcher->setExtractor(
            static function (Request $request) {
                return $request->getMethod();
            }
        );
        $this->matcher[] = $matcher;

        return $this;
    }

    public function callback(Closure $callback)
    {
        $this->matcher[] = $this->matcherFactory->closure($callback);

        return $this;
    }

    /**
     * @return SerializableClosure[]
     */
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
}
