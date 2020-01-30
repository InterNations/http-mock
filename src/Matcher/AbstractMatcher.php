<?php

namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use Psr\Http\Message\RequestInterface as Request;
use SuperClosure\SerializableClosure;

abstract class AbstractMatcher implements MatcherInterface
{
    protected $extractor;

    public function setExtractor(Closure $extractor)
    {
        $this->extractor = $extractor;
    }

    protected function createExtractor()
    {
        if (!$this->extractor) {
            return static function (Request $request) {
                return $request;
            };
        }

        return $this->extractor;
    }

    abstract protected function createMatcher();

    public function getMatcher()
    {
        $matcher = new SerializableClosure($this->createMatcher());
        $extractor = new SerializableClosure($this->createExtractor());

        return new SerializableClosure(
            static function (Request $request) use ($matcher, $extractor) {
                return $matcher($extractor($request));
            }
        );
    }
}
