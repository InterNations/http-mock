<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;

class RegexMatcher extends ExtractorBasedMatcher
{
    private string $regex;

    public function __construct(string $regex)
    {
        $this->regex = $regex;
    }

    protected function createMatcher(): Closure
    {
        $regex = $this->regex;

        return static function ($value) use ($regex) {
            return (bool) preg_match($regex, $value);
        };
    }
}
