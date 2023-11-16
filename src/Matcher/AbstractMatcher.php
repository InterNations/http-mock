<?php

namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use Opis\Closure\SerializableClosure;
use Psr\Http\Message\RequestInterface as Request;

abstract class AbstractMatcher implements MatcherInterface
{
    protected ?Closure $extractor = null;

    public function setExtractor(Closure $extractor) : void
    {
        $this->extractor = $extractor;
    }

    protected function createExtractor() : Closure
    {
        if (!$this->extractor) {
            return static function (Request $request) {
                return $request;
            };
        }

        return $this->extractor;
    }

    abstract protected function createMatcher(): callable;

    public function getMatcher() : SerializableClosure
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
