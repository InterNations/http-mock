<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;

class ClosureMatcher extends AbstractMatcher
{
    private $closure;

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    protected function createMatcher()
    {
        return $this->closure;
    }
}
