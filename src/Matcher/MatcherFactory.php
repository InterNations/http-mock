<?php

namespace InterNations\Component\HttpMock\Matcher;

use Closure;

class MatcherFactory
{
    public function regex(string $regex): RegexMatcher
    {
        return new RegexMatcher($regex);
    }

    public function str(string $string): StringMatcher
    {
        return new StringMatcher($string);
    }

    public function closure(Closure $closure): ClosureMatcher
    {
        return new ClosureMatcher($closure);
    }
}
