<?php

namespace InterNations\Component\HttpMock\Matcher;

class RegexMatcher extends AbstractMatcher
{
    private string $regex;

    public function __construct(string $regex)
    {
        $this->regex = $regex;
    }

    protected function createMatcher(): callable
    {
        $regex = $this->regex;

        return static function ($value) use ($regex) {
            return (bool) preg_match($regex, $value);
        };
    }
}
