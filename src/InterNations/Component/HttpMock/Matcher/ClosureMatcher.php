<?php
namespace InterNations\Component\HttpMock\Matcher;

use Guzzle\Http\Message\Request;
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
