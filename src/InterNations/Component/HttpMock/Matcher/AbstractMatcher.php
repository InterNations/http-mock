<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use Jeremeamia\SuperClosure\SerializableClosure;

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
            // @codingStandardsIgnoreStart
            return static function (\Symfony\Component\HttpFoundation\Request $request) {
            // @codingStandardsIgnoreEnd
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

        return new SerializableClosure(function($request) use ($matcher, $extractor) {
            return $matcher($extractor($request));
        });

        return new SerializableClosure(
            // @codingStandardsIgnoreStart
            static function (\Symfony\Component\HttpFoundation\Request $request) use ($matcher, $extractor) {
            // @codingStandardsIgnoreEnd
                return $matcher($extractor($request));
            }
        );
    }
}
