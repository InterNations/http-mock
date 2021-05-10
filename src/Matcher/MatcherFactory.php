<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;

final class MatcherFactory
{
    public function regex(string $regex): Matcher
    {
        return new RegexMatcher($regex);
    }

    public function str(string $string): Matcher
    {
        return new StringMatcher($string);
    }

    public function closure(Closure $closure): Matcher
    {
        return new ClosureMatcher($closure);
    }
}
