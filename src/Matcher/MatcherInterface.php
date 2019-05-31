<?php
namespace Pagely\Component\HttpMock\Matcher;

use SuperClosure\SerializableClosure;
use Closure;

interface MatcherInterface
{
    /**
     * @return SerializableClosure
     */
    public function getMatcher();

    public function setExtractor(Closure $extractor);
}
