<?php
namespace Pagely\Component\HttpMock\Matcher;

class RegexMatcher extends AbstractMatcher
{
    private $regex;

    public function __construct($regex)
    {
        $this->regex = $regex;
    }

    protected function createMatcher()
    {
        $regex = $this->regex;

        return static function ($value) use ($regex) {
            return (bool) preg_match($regex, $value);
        };
    }
}
