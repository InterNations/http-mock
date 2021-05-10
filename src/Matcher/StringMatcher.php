<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;

class StringMatcher extends AbstractMatcher
{
    private string $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    protected function createMatcher(): Closure
    {
        $string = $this->string;

        return static function ($value) use ($string) {
            return (string) $string === (string) $value;
        };
    }
}
