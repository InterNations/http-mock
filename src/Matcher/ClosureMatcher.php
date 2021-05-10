<?php
namespace InterNations\Component\HttpMock\Matcher;

use Closure;

class ClosureMatcher extends AbstractMatcher
{
    private Closure $closure;

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    protected function createMatcher(): Closure
    {
        return $this->closure;
    }
}
