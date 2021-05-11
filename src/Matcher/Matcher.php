<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;
use Opis\Closure\SerializableClosure;

interface Matcher
{
    public function getMatcher(): SerializableClosure;
    public function setExtractor(Closure $extractor): void;
}
