<?php
namespace InterNations\Component\HttpMock\Matcher;

use Guzzle\Http\Message\Request;

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
