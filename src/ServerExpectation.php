<?php
namespace InterNations\Component\HttpMock;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ServerExpectation
{
    /** @var list<callable> */
    private array $matchers;
    private Response $response;
    /** @var callable|null */
    private $limiter;
    private int $runs;

    /** @param list<callable> $matchers */
    public function __construct(array $matchers, Response $response, ?callable $limiter, int $runs)
    {
        $this->matchers = $matchers;
        $this->response = $response;
        $this->limiter = $limiter;
        $this->runs = $runs;
    }

    public function matchRequest(Request $currentRequest): ?Response
    {
        if (!$this->matches($currentRequest)) {
            return null;
        }

        try {
            return $this->isApplicable() ? $this->response : null;
        } finally {
            $this->runs++;
        }
    }

    private function matches(Request $currentRequest): bool
    {
        foreach ($this->matchers as $matcher) {
            if (!$matcher($currentRequest)) {
                return false;
            }
        }

        return true;
    }

    private function isApplicable(): bool
    {
        $limiter = $this->limiter;
        return $limiter === null || $limiter($this->runs);
    }
}
