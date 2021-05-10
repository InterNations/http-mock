<?php
namespace InterNations\Component\HttpMock\Matcher;

use SuperClosure\SerializableClosure;
use Closure;

interface Matcher
{
    public function getMatcher(): SerializableClosure;
    public function setExtractor(Closure $extractor): void;
}
