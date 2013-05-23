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
            return static function (\Guzzle\Http\Message\Request $request) {
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
            function (\Guzzle\Http\Message\Request $request) use ($matcher, $extractor) {
                return $matcher($extractor($request));
            }
        );
    }
}
