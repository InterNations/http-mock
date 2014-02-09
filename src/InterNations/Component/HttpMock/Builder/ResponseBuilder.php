<?php
namespace InterNations\Component\HttpMock\Builder;

use InterNations\Component\HttpMock\Response\CallbackResponse;
use Closure;
use Jeremeamia\SuperClosure\SerializableClosure;

class ResponseBuilder
{
    /** @var MockBuilder */
    private $mockBuilder;

    /** @var StubBuilder */
    private $stubBuilder;

    public function __construct(MockBuilder $mockBuilder)
    {
        $this->mockBuilder = $mockBuilder;
        $this->stubBuilder = new StubBuilder($this);
    }

    public function stub()
    {
        return $this->stubBuilder;
    }

    public function statusCode($statusCode)
    {
        $this->stubBuilder->statusCode($statusCode);

        return $this;
    }

    public function body($body)
    {
        $this->stubBuilder->body($body);

        return $this;
    }

    public function callback(Closure $callback)
    {
        $this->stubBuilder->callback($callback);

        return $this;
    }

    public function header($header, $value)
    {
        $this->stubBuilder->header($header, $value);

        return $this;
    }

    public function end()
    {
        return $this->mockBuilder;
    }

    public function getResponse()
    {
        return $this->stubBuilder->getResponse();
    }
}
