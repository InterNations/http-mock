<?php
namespace InterNations\Component\HttpMock\Matcher;

use Jeremeamia\SuperClosure\SerializableClosure;
use Closure;

interface MatcherInterface
{
    /**
     * @return SerializableClosure
     */
    public function getMatcher();

    public function setExtractor(Closure $extractor);
}
