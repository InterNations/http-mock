<?php
namespace InterNations\Component\HttpMock\Matcher;

use Guzzle\Http\Message\Request;

class StringMatcher extends AbstractMatcher
{
    private $string;

    public function __construct($string)
    {
        $this->string = $string;
    }

    protected function createMatcher()
    {
        $string = $this->string;

        return static function ($value) use ($string) {
            return $string === $value;
        };
    }
}
