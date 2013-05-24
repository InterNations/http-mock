<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use SuperClosure\SuperClosure;

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
        $matcher = new SuperClosure($this->createMatcher());
        $extractor = new SuperClosure($this->createExtractor());

        return new SuperClosure(
            // @codingStandardsIgnoreStart
            static function (\Symfony\Component\HttpFoundation\Request $request) use ($matcher, $extractor) {
            // @codingStandardsIgnoreEnd
                return $matcher($extractor($request));
            }
        );
    }
}
