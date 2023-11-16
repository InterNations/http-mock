<?php

namespace InterNations\Component\HttpMock\Matcher;

class StringMatcher extends AbstractMatcher
{
    private string $string;

    public function __construct(string $string)
    {
        $this->string = $string;
    }

    protected function createMatcher(): callable
    {
        $string = $this->string;

        return static function ($value) use ($string) {
            return (string) $string === (string) $value;
        };
    }
}
