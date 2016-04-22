<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;

class MatcherFactory
{
    public function regex($regex)
    {
        return new RegexMatcher($regex);
    }

    public function str($string)
    {
        return new StringMatcher($string);
    }

    public function closure(Closure $closure)
    {
        return new ClosureMatcher($closure);
    }
}
