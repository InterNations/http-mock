<?php
namespace InterNations\Component\HttpMock\Builder;

use Closure;

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

    /** @return StubBuilder */
    public function stub()
    {
        return $this->stubBuilder;
    }

    /**
     * @param integer $statusCode
     * @deprecated Call stub() before to mark matched path as stubbed
     * @return ResponseBuilder
     */
    public function statusCode($statusCode)
    {
        $this->stubBuilder->statusCode($statusCode);

        return $this;
    }

    /**
     * @param string $body
     * @deprecated Call stub() before to mark matched path as stubbed
     * @return ResponseBuilder
     */
    public function body($body)
    {
        $this->stubBuilder->body($body);

        return $this;
    }

    /**
     * @param Closure $callback
     * @deprecated Call stub() before to mark matched path as stubbed
     * @return ResponseBuilder
     */
    public function callback(Closure $callback)
    {
        $this->stubBuilder->callback($callback);

        return $this;
    }

    /**
     * @param string $header
     * @param string $value
     * @deprecated Call stub() before to mark matched path as stubbed
     * @return ResponseBuilder
     */
    public function header($header, $value)
    {
        $this->stubBuilder->header($header, $value);

        return $this;
    }

    /** @return MockBuilder */
    public function end()
    {
        return $this->mockBuilder;
    }

    public function getResponse()
    {
        return $this->stubBuilder->getResponse();
    }
}
