<?php
namespace InterNations\Component\HttpMock\Matcher;

use SuperClosure\SuperClosure;
use Closure;

interface MatcherInterface
{
    /**
     * @return SuperClosure
     */
    public function getMatcher();

    public function setExtractor(Closure $extractor);
}
