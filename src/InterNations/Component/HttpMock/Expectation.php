<?php
namespace InterNations\Component\HttpMock;

use Symfony\Component\HttpFoundation\Response;
use InterNations\Component\HttpMock\Matcher\MatcherFactory;
use InterNations\Component\HttpMock\Matcher\MatcherInterface;
use SuperClosure\SuperClosure;
use Closure;

class Expectation
{
    /**
     * @var MatcherInterface[]
     */
    private $matcher = [];

    /**
     * @var MockBuilder
     */
    private $mockBuilder;

    /**
     * @var MatcherFactory
     */
    private $matcherFactory;

    /**
     * @var Response
     */
    private $response;

    public function __construct(MockBuilder $mockBuilder, MatcherFactory $matcherFactory)
    {
        $this->mockBuilder = $mockBuilder;
        $this->matcherFactory = $matcherFactory;
        $this->response = new Response(200);
    }

    public function pathIs($matcher)
    {
        if (!$matcher instanceof MatcherInterface) {
            $matcher = $this->matcherFactory->str($matcher);
        }
        $matcher->setExtractor(static function (\Symfony\Component\HttpFoundation\Request $request) {
            return $request->getPathInfo();
        });
        $this->matcher[] = $matcher;

        return $this;
    }

    public function methodIs($matcher)
    {
        if (!$matcher instanceof MatcherInterface) {
            $matcher = $this->matcherFactory->str($matcher);
        }
        $matcher->setExtractor(static function (\Symfony\Component\HttpFoundation\Request $request) {
            return $request->getMethod();
        });
        $this->matcher[] = $matcher;

        return $this;
    }

    public function callback(Closure $callback)
    {
        $this->matcher[] = $this->matcherFactory->closure($callback);

        return $this;
    }

    /**
     * @return SuperClosure[]
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
        return $this;
    }

    public function statusCode($statusCode)
    {
        $this->response->setStatusCode($statusCode);

        return $this;
    }

    public function body($body)
    {
        $this->response->setContent($body);

        return $this;
    }

    public function header($header, $value)
    {
        $this->response->headers->set($header, $value);

        return $this;
    }

    public function end()
    {
        return $this->mockBuilder;
    }

    public function getResponse()
    {
        return $this->response;
    }
}
