<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use Opis\Closure\SerializableClosure;

interface MatcherInterface
{
    /**
     * @return SerializableClosure
     */
    public function getMatcher();

    public function setExtractor(Closure $extractor);
}
